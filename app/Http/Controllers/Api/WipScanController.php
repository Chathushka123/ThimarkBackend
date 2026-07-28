<?php

namespace App\Http\Controllers\Api;

use App\DailyShiftTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\LookupBundleTicketRequest;
use App\Http\Requests\ReturnFromReworkRequest;
use App\Http\Requests\ScanBundleTicketRequest;
use App\Http\Requests\SendToReworkRequest;
use App\Models\Bundle;
use App\Models\BundleTicket;
use App\Models\BundleTicketReject;
use App\Models\BundleTicketRework;
use App\Models\BundleTicketReworkReturn;
use App\Models\BundleTicketSecondary;
use App\Models\Reason;
use App\Models\UserOperation;
use App\Models\WorkOrderOperation;
use App\Services\BundleLedgerService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Handles the Production WIP Scanning screen: a line operator scans a
 * bundle's QR/barcode at their assigned Operation, on behalf of their
 * currently active shift team. Bundle tickets (one per bundle x route step
 * x direction) are pre-generated elsewhere with a planned qty; scanning
 * records actual quantity against bundle_ticket_secondaries (good),
 * bundle_ticket_rejects (defective), and bundle_ticket_reworks (temporarily
 * pulled aside for the rework team).
 *
 * Quantity ledger: a ticket's qty is "accounted for" once
 * scanned + rejected + outstanding-rework reaches its planned qty.
 * Outstanding rework isn't final — when the rework team resolves it via
 * returnFromRework(), the resolved qty becomes an *additional*
 * bundle_ticket_secondary (good, source=REWORK_RETURN) and/or
 * bundle_ticket_reject (bad, source=REWORK_REJECT) row on the SAME ticket
 * it came from, so the ledger below picks it up with no special-casing.
 *
 * Sequencing is no longer an all-or-nothing "is the previous operation
 * fully done" gate: the next operation's available qty is capped at
 * whatever good qty the previous step has *released so far*
 * (scanned, not accounted — rejects and outstanding rework never flow
 * downstream). This lets partially-processed bundles keep moving while a
 * subset is stuck in rework, exactly as the shop floor works.
 *
 * A scan is a two-step flow: lookup() resolves the target ticket and its
 * remaining qty for the operator to review/amend, then scan() performs the
 * actual write once they confirm. Both share resolveScanTarget() for the
 * validation/lookup logic so the two can never drift apart.
 */
class WipScanController extends Controller
{
    private BundleLedgerService $ledger;

    public function __construct(BundleLedgerService $ledger)
    {
        $this->ledger = $ledger;
    }

    /**
     * List the operations the authenticated user is assigned to scan for.
     */
    public function myOperations(): JsonResponse
    {
        try {
            $operations = Auth::user()->operations()
                ->orderBy('operation_masters.operation_code')
                ->get(['operation_masters.id', 'operation_masters.operation_code', 'operation_masters.description']);

            return $this->success('Assigned operations retrieved successfully.', $operations);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve assigned operations.', $e->getMessage(), 500);
        }
    }

    /**
     * List the shift teams currently active (now falls within their
     * start/end window) that this user is assigned to work as (see
     * App\Models\UserTeam) — the same pattern as myOperations().
     */
    public function myTeams(): JsonResponse
    {
        try {
            $now = now();
            $teamIds = Auth::user()->teams()->pluck('teams.id');

            $teams = DailyShiftTeam::with(['team', 'dailyShift.shift'])
                ->where('active', true)
                ->where('start_date_time', '<=', $now)
                ->where('end_date_time', '>=', $now)
                ->whereIn('team_id', $teamIds)
                ->orderBy('start_date_time')
                ->get()
                ->map(function (DailyShiftTeam $dst) {
                    return [
                        'id' => $dst->id,
                        'team_code' => optional($dst->team)->team_code,
                        'team_name' => optional($dst->team)->team_name,
                        'shift_date' => optional(optional($dst->dailyShift)->shift_date)->format('Y-m-d'),
                        'shift_code' => optional(optional($dst->dailyShift)->shift)->shift_code,
                        'shift_name' => optional(optional($dst->dailyShift)->shift)->shift_name,
                    ];
                });

            return $this->success('Active shift teams retrieved successfully.', $teams);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve active shift teams.', $e->getMessage(), 500);
        }
    }

    /**
     * Active reasons for the reject/rework reason dropdowns.
     */
    public function reasons(string $type): JsonResponse
    {
        try {
            $type = strtoupper($type);
            if (!in_array($type, ['REJECT', 'REWORK'], true)) {
                return $this->error('Unknown reason type.', ['code' => 'UNKNOWN_REASON_TYPE'], 422);
            }

            $reasons = Reason::where('type', $type)
                ->where('active', true)
                ->orderBy('description')
                ->get(['id', 'code', 'description']);

            return $this->success('Reasons retrieved successfully.', $reasons);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve reasons.', $e->getMessage(), 500);
        }
    }

    /**
     * Resolve a scanned bundle's target ticket for the given operation
     * WITHOUT recording anything — lets the operator review/amend the
     * quantity before it's actually scanned.
     */
    public function lookup(LookupBundleTicketRequest $request): JsonResponse
    {
        $operationId = (int) $request->input('operation_id');

        $isAssigned = UserOperation::where('user_id', Auth::id())
            ->where('operation_id', $operationId)
            ->where('active', true)
            ->exists();

        if (!$isAssigned) {
            return $this->error("This operation isn't assigned to you.", ['code' => 'NOT_YOUR_OPERATION'], 403);
        }

        $bundleId = $this->parseBundleId($request->input('ticket_code'));
        if ($bundleId === null) {
            return $this->error('Unrecognised bundle code.', ['code' => 'UNKNOWN_TICKET'], 422);
        }

        try {
            $direction = $request->filled('direction') ? strtoupper($request->input('direction')) : null;
            $target = $this->resolveScanTarget($operationId, $bundleId, false, false, $direction);
            if (isset($target['error'])) {
                return $target['error'];
            }

            return $this->success('Ticket found.', [
                'bundle_id' => $target['bundle']->id,
                'work_order_id' => $target['bundle']->work_order_id,
                'bundle_ticket_id' => $target['ticket']->id,
                'direction' => $target['direction'],
                'remaining' => $target['remaining'],
                'operation_code' => $target['rom']->operation->operation_code ?? null,
                'operation_description' => $target['rom']->operation->description ?? null,
                'seq' => $target['rom']->seq,
                'available_directions' => $target['availableDirections'],
                'previous_step' => $target['previousStep'],
            ]);
        } catch (Exception $e) {
            return $this->error('Failed to look up bundle.', $e->getMessage(), 500);
        }
    }

    /**
     * Record a scan against the bundle ticket for the given operation,
     * auto-detecting IN vs OUT and enforcing the quantity ledger described
     * on the class docblock. Accepts an optional 3-way split: good qty,
     * reject qty, and rework qty, all recorded atomically.
     */
    public function scan(ScanBundleTicketRequest $request): JsonResponse
    {
        $operationId = (int) $request->input('operation_id');
        $dailyShiftTeamId = (int) $request->input('daily_shift_team_id');

        $isAssigned = UserOperation::where('user_id', Auth::id())
            ->where('operation_id', $operationId)
            ->where('active', true)
            ->exists();

        if (!$isAssigned) {
            return $this->error("This operation isn't assigned to you.", ['code' => 'NOT_YOUR_OPERATION'], 403);
        }

        $bundleId = $this->parseBundleId($request->input('ticket_code'));
        if ($bundleId === null) {
            return $this->error('Unrecognised bundle code.', ['code' => 'UNKNOWN_TICKET'], 422);
        }

        try {
            return DB::transaction(function () use ($bundleId, $operationId, $dailyShiftTeamId, $request) {
                // Lock the bundle + its tickets so two near-simultaneous
                // scans of the same bundle can't both target the same
                // ticket unsafely. An explicit direction (from the operator
                // switching IN/OUT on the scanning screen) targets that
                // specific ticket instead of auto-detecting.
                $direction = $request->filled('direction') ? strtoupper($request->input('direction')) : null;
                $target = $this->resolveScanTarget($operationId, $bundleId, true, false, $direction);
                if (isset($target['error'])) {
                    return $target['error'];
                }

                $ticket = $target['ticket'];
                $rom = $target['rom'];
                $direction = $target['direction'];
                $remaining = $target['remaining'];

                $scanQty = $request->filled('scan_qty') ? (int) $request->input('scan_qty') : null;
                $rejectQty = (int) $request->input('reject_qty', 0);
                $reworkQty = (int) $request->input('rework_qty', 0);

                if ($scanQty === null) {
                    // Fast single-tap path: scan everything not already spoken
                    // for by the reject/rework qty on this same request.
                    $scanQty = max($remaining - $rejectQty - $reworkQty, 0);
                }

                if ($scanQty < 0 || $rejectQty < 0 || $reworkQty < 0) {
                    return $this->error('Quantities cannot be negative.', ['code' => 'INVALID_QTY'], 422);
                }

                if ($scanQty + $rejectQty + $reworkQty <= 0) {
                    return $this->error('Enter a scanned, rejected, or rework quantity.', ['code' => 'INVALID_QTY'], 422);
                }

                if ($scanQty + $rejectQty + $reworkQty > $remaining) {
                    return $this->error("Only {$remaining} left on this ticket.", ['code' => 'QTY_EXCEEDS_TICKET'], 422);
                }

                $secondary = null;
                $reject = null;
                $rework = null;

                if ($scanQty > 0) {
                    $secondary = BundleTicketSecondary::create([
                        'bundle_ticket_id' => $ticket->id,
                        'scan_qty' => $scanQty,
                        'source' => 'SCAN',
                        'daily_shift_team_id' => $dailyShiftTeamId,
                        'active' => true,
                    ]);
                }

                if ($rejectQty > 0) {
                    $reject = BundleTicketReject::create([
                        'bundle_ticket_id' => $ticket->id,
                        'reject_qty' => $rejectQty,
                        'reject_reason' => $request->input('reject_reason'),
                        'reason_id' => $request->input('reject_reason_id'),
                        'source' => 'DIRECT',
                        'daily_shift_team_id' => $dailyShiftTeamId,
                        'active' => true,
                    ]);
                }

                if ($reworkQty > 0) {
                    $rework = BundleTicketRework::create([
                        'bundle_ticket_id' => $ticket->id,
                        'rework_qty' => $reworkQty,
                        'reason_id' => $request->input('rework_reason_id'),
                        'remarks' => $request->input('rework_remarks'),
                        'daily_shift_team_id' => $dailyShiftTeamId,
                        'active' => true,
                    ]);
                }

                // Recompute the ledger fresh so progress/completion below
                // reflects the scan just written (cheap: same bundle, still
                // inside the lock).
                $after = $this->resolveScanTarget($operationId, $bundleId, false, true);

                return $this->success('Scan recorded.', [
                    'bundle_id' => $target['bundle']->id,
                    'work_order_id' => $target['bundle']->work_order_id,
                    'bundle_ticket_id' => $ticket->id,
                    'direction' => $direction,
                    'scan_qty' => $scanQty,
                    'reject_qty' => $rejectQty,
                    'rework_qty' => $reworkQty,
                    'secondary_id' => optional($secondary)->id,
                    'reject_id' => optional($reject)->id,
                    'rework_id' => optional($rework)->id,
                    'reject_reason_id' => $rejectQty > 0 ? $request->input('reject_reason_id') : null,
                    'rework_reason_id' => $reworkQty > 0 ? $request->input('rework_reason_id') : null,
                    'remaining_after' => $after['ticketRemaining'][$ticket->id] ?? 0,
                    'ticket_complete' => ($after['ticketRemaining'][$ticket->id] ?? 0) <= 0,
                    'operation_code' => $rom->operation->operation_code ?? null,
                    'operation_description' => $rom->operation->description ?? null,
                    'seq' => $rom->seq,
                    'previous_step' => $target['previousStep'],
                    'progress' => $after['progress'],
                    'bundle_complete' => $after['progress']['completed'] === $after['progress']['total'],
                    'created_at' => now()->toIso8601String(),
                ], 201);
            });
        } catch (Exception $e) {
            return $this->error('Failed to record scan.', $e->getMessage(), 500);
        }
    }

    /**
     * Pull a qty out of the normal flow at the operator's current
     * operation/direction and send it to the rework team. Capped by the
     * same "remaining" the operator would otherwise scan/reject against.
     */
    public function sendToRework(SendToReworkRequest $request): JsonResponse
    {
        $operationId = (int) $request->input('operation_id');
        $dailyShiftTeamId = (int) $request->input('daily_shift_team_id');

        $isAssigned = UserOperation::where('user_id', Auth::id())
            ->where('operation_id', $operationId)
            ->where('active', true)
            ->exists();

        if (!$isAssigned) {
            return $this->error("This operation isn't assigned to you.", ['code' => 'NOT_YOUR_OPERATION'], 403);
        }

        $bundleId = $this->parseBundleId($request->input('ticket_code'));
        if ($bundleId === null) {
            return $this->error('Unrecognised bundle code.', ['code' => 'UNKNOWN_TICKET'], 422);
        }

        try {
            return DB::transaction(function () use ($bundleId, $operationId, $dailyShiftTeamId, $request) {
                $direction = $request->filled('direction') ? strtoupper($request->input('direction')) : null;
                $target = $this->resolveScanTarget($operationId, $bundleId, true, false, $direction);
                if (isset($target['error'])) {
                    return $target['error'];
                }

                $reworkQty = (int) $request->input('rework_qty');
                if ($reworkQty > $target['remaining']) {
                    return $this->error("Only {$target['remaining']} left on this ticket.", ['code' => 'QTY_EXCEEDS_TICKET'], 422);
                }

                $rework = BundleTicketRework::create([
                    'bundle_ticket_id' => $target['ticket']->id,
                    'rework_qty' => $reworkQty,
                    'reason_id' => $request->input('reason_id'),
                    'remarks' => $request->input('remarks'),
                    'daily_shift_team_id' => $dailyShiftTeamId,
                    'active' => true,
                ]);

                return $this->success('Sent to rework.', [
                    'id' => $rework->id,
                    'bundle_id' => $target['bundle']->id,
                    'bundle_ticket_id' => $target['ticket']->id,
                    'direction' => $target['direction'],
                    'rework_qty' => $reworkQty,
                ], 201);
            });
        } catch (Exception $e) {
            return $this->error('Failed to send to rework.', $e->getMessage(), 500);
        }
    }

    /**
     * The rework team's own scan-out: record how much of an outstanding
     * rework qty came back good (return_qty) vs was permanently rejected
     * after rework (reject_qty). This is looked up directly by
     * bundle_ticket_id (from the outstandingRework() queue) rather than by
     * re-walking the route — the rework team isn't part of the sequence,
     * they resolve whatever the floor sent them.
     */
    public function returnFromRework(ReturnFromReworkRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $ticketId = (int) $request->input('bundle_ticket_id');
                $ticket = BundleTicket::where('id', $ticketId)->lockForUpdate()->first();
                if (!$ticket || !$ticket->active) {
                    return $this->error('Bundle ticket not found.', ['code' => 'UNKNOWN_TICKET'], 404);
                }

                $sent = (int) BundleTicketRework::where('bundle_ticket_id', $ticketId)
                    ->where('active', true)
                    ->sum('rework_qty');
                $returned = (int) BundleTicketReworkReturn::where('bundle_ticket_id', $ticketId)
                    ->where('active', true)
                    ->selectRaw('COALESCE(SUM(return_qty + reject_qty), 0) as total')
                    ->value('total');
                $outstanding = max(0, $sent - $returned);

                $returnQty = (int) $request->input('return_qty', 0);
                $rejectQty = (int) $request->input('reject_qty', 0);

                if ($returnQty + $rejectQty > $outstanding) {
                    return $this->error("Only {$outstanding} outstanding on this rework batch.", ['code' => 'QTY_EXCEEDS_OUTSTANDING'], 422);
                }

                $dailyShiftTeamId = (int) $request->input('daily_shift_team_id');
                $secondary = null;
                $reject = null;

                if ($returnQty > 0) {
                    $secondary = BundleTicketSecondary::create([
                        'bundle_ticket_id' => $ticketId,
                        'scan_qty' => $returnQty,
                        'source' => 'REWORK_RETURN',
                        'daily_shift_team_id' => $dailyShiftTeamId,
                        'active' => true,
                    ]);
                }

                if ($rejectQty > 0) {
                    $reject = BundleTicketReject::create([
                        'bundle_ticket_id' => $ticketId,
                        'reject_qty' => $rejectQty,
                        'reject_reason' => $request->input('remarks'),
                        'reason_id' => $request->input('reason_id'),
                        'source' => 'REWORK_REJECT',
                        'daily_shift_team_id' => $dailyShiftTeamId,
                        'active' => true,
                    ]);
                }

                $return = BundleTicketReworkReturn::create([
                    'bundle_ticket_id' => $ticketId,
                    'return_qty' => $returnQty,
                    'reject_qty' => $rejectQty,
                    'reason_id' => $request->input('reason_id'),
                    'remarks' => $request->input('remarks'),
                    'daily_shift_team_id' => $dailyShiftTeamId,
                    'bundle_ticket_secondary_id' => optional($secondary)->id,
                    'bundle_ticket_reject_id' => optional($reject)->id,
                    'active' => true,
                ]);

                return $this->success('Rework return recorded.', [
                    'id' => $return->id,
                    'bundle_ticket_id' => $ticketId,
                    'return_qty' => $returnQty,
                    'reject_qty' => $rejectQty,
                    'outstanding_after' => $outstanding - $returnQty - $rejectQty,
                ], 201);
            });
        } catch (Exception $e) {
            return $this->error('Failed to record rework return.', $e->getMessage(), 500);
        }
    }

    /**
     * Queue for the rework team: every bundle ticket with an outstanding
     * (not yet returned) rework qty.
     */
    public function outstandingRework(): JsonResponse
    {
        try {
            $sentTotals = BundleTicketRework::where('active', true)
                ->groupBy('bundle_ticket_id')
                ->selectRaw('bundle_ticket_id, SUM(rework_qty) as total')
                ->pluck('total', 'bundle_ticket_id');

            $returnedTotals = BundleTicketReworkReturn::where('active', true)
                ->groupBy('bundle_ticket_id')
                ->selectRaw('bundle_ticket_id, SUM(return_qty + reject_qty) as total')
                ->pluck('total', 'bundle_ticket_id');

            $outstandingTicketIds = $sentTotals->filter(function ($sent, $ticketId) use ($returnedTotals) {
                return $sent - (int) ($returnedTotals[$ticketId] ?? 0) > 0;
            })->keys();

            $withChain = 'workOrderOperation.routingOperationMaster.operation';
            $tickets = BundleTicket::with([
                $withChain,
                'bundle.workOrder.batchDetail.batch',
                'bundle.workOrder.batchDetail.model.mainModel',
            ])
                ->whereIn('id', $outstandingTicketIds)
                ->get()
                ->map(function (BundleTicket $ticket) use ($sentTotals, $returnedTotals) {
                    $rom = optional(optional($ticket->workOrderOperation)->routingOperationMaster);
                    $outstanding = (int) ($sentTotals[$ticket->id] ?? 0) - (int) ($returnedTotals[$ticket->id] ?? 0);

                    // Same main-model / model / batch hierarchy as
                    // WipDashboardController: bundle -> work_order ->
                    // batch_detail -> batch/model -> main_model.
                    $batchDetail = optional(optional($ticket->bundle)->workOrder)->batchDetail;
                    $modelRecord = optional($batchDetail)->model;
                    $mainModelRecord = optional($modelRecord)->mainModel;

                    return [
                        'bundle_ticket_id' => $ticket->id,
                        'bundle_id' => $ticket->bundle_id,
                        'work_order_id' => optional($ticket->bundle)->work_order_id,
                        'batch_no' => optional(optional($batchDetail)->batch)->batch_no,
                        'model_name' => optional($modelRecord)->name,
                        'main_model_name' => optional($mainModelRecord)->name,
                        'direction' => $ticket->direction,
                        'operation_code' => optional($rom->operation)->operation_code,
                        'operation_description' => optional($rom->operation)->description,
                        'outstanding_qty' => max(0, $outstanding),
                    ];
                })
                ->values();

            return $this->success('Outstanding rework retrieved successfully.', $tickets);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve outstanding rework.', $e->getMessage(), 500);
        }
    }

    /**
     * Supervisor/QA reconciliation view for a bundle: per-ticket ledger
     * breakdown plus the bundle-level invariant check — total accounted
     * for (scanned + rejected + outstanding rework) must equal bundle qty
     * on every ticket, and the bundle is only "complete" once nothing is
     * outstanding anywhere on its route.
     */
    public function reconciliation(int $bundleId): JsonResponse
    {
        try {
            $bundle = Bundle::where('id', $bundleId)->first();
            if (!$bundle) {
                return $this->error('Bundle not found.', ['code' => 'UNKNOWN_BUNDLE'], 404);
            }

            $ledger = $this->ledger->build($bundle);

            $tickets = $ledger['tickets']->map(function (BundleTicket $ticket) use ($ledger) {
                $rom = optional(optional($ticket->workOrderOperation)->routingOperationMaster);

                return [
                    'bundle_ticket_id' => $ticket->id,
                    'operation_code' => optional($rom->operation)->operation_code,
                    'operation_description' => optional($rom->operation)->description,
                    'seq' => $rom->seq,
                    'direction' => $ticket->direction,
                    'qty' => $ticket->qty,
                    'effective_qty' => $ledger['effectiveQty']($ticket),
                    'scanned' => $ledger['scanned']($ticket),
                    'rejected' => $ledger['rejected']($ticket),
                    'outstanding_rework' => $ledger['outstandingRework']($ticket),
                    'resolved' => $ledger['ticketResolved']($ticket),
                ];
            })->values();

            $totalRejected = $tickets->sum('rejected');
            $totalOutstandingRework = $tickets->sum('outstanding_rework');
            $isComplete = $tickets->every(fn ($t) => $t['resolved']);

            return $this->success('Reconciliation retrieved successfully.', [
                'bundle_id' => $bundle->id,
                'bundle_qty' => $bundle->qty,
                'total_rejected' => $totalRejected,
                'total_outstanding_rework' => $totalOutstandingRework,
                'is_complete' => $isComplete,
                'tickets' => $tickets,
            ]);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve reconciliation.', $e->getMessage(), 500);
        }
    }

    /**
     * Resolve the target bundle ticket for a scan/lookup/sendToRework:
     * bundle lookup, quantity-ledger computation, and IN/OUT ticket
     * resolution. Shared by lookup() (read-only), scan() and
     * sendToRework() (which lock rows for the write).
     *
     * @param string|null $requestedDirection When given ('IN' or 'OUT'), resolves that
     *        specific direction only instead of auto-detecting — lets an operator
     *        explicitly switch direction on the scanning screen rather than being
     *        forced through IN before OUT becomes reachable.
     * @return array{error: JsonResponse}|array{bundle: Bundle, ticket: BundleTicket, direction: string, remaining: int, rom: mixed, ticketRemaining: array<int,int>, progress: array{completed:int,total:int}, availableDirections: array{in: bool, out: bool}, previousStep: array|null}
     */
    private function resolveScanTarget(int $operationId, int $bundleId, bool $forWrite, bool $skipCurrentOp = false, ?string $requestedDirection = null): array
    {
        $bundleQuery = Bundle::where('id', $bundleId)->where('active', true);
        if ($forWrite) {
            $bundleQuery->lockForUpdate();
        }
        $bundle = $bundleQuery->first();
        if (!$bundle) {
            return ['error' => $this->error('Bundle not found.', ['code' => 'UNKNOWN_BUNDLE'], 404)];
        }

        $ledger = $this->ledger->build($bundle, $forWrite);
        $workOrderOperations = $ledger['workOrderOperations'];
        $ticketFor = $ledger['ticketFor'];
        $scanned = $ledger['scanned'];
        $ticketRemainingFn = $ledger['ticketRemaining'];
        $entryCapBySeq = $ledger['entryCapBySeq'];
        $resolved = $ledger['resolved'];

        $progress = [
            'completed' => $workOrderOperations->filter($resolved)->count(),
            'total' => $workOrderOperations->count(),
        ];
        $ticketRemainingMap = $ledger['tickets']->mapWithKeys(fn (BundleTicket $t) => [$t->id => $ticketRemainingFn($t)]);

        if ($skipCurrentOp) {
            // Only the post-write ledger snapshot was needed (used by scan()
            // to report progress after writing) — no ticket resolution.
            return ['ticketRemaining' => $ticketRemainingMap, 'progress' => $progress];
        }

        $currentWoo = $workOrderOperations->first(
            fn ($woo) => $woo->routingOperationMaster && (int) $woo->routingOperationMaster->operation_id === $operationId
        );

        if (!$currentWoo) {
            return ['error' => $this->error("This operation isn't part of the bundle's route.", ['code' => 'NO_MATCHING_OPERATION'], 422)];
        }

        $rom = $currentWoo->routingOperationMaster;
        $seq = (int) $rom->seq;
        $entryCap = $entryCapBySeq[$seq] ?? null; // null = uncapped (first seq in the route)
        $availableDirections = ['in' => (bool) $rom->in, 'out' => (bool) $rom->out];

        $capped = fn (int $qty, ?int $cap) => $cap === null ? $qty : min($qty, $cap);
        $rejected = $ledger['rejected'];
        $outstandingRework = $ledger['outstandingRework'];

        // Names the operation/direction a given direction's qty is sourced
        // from — the OUT ticket's is this same operation's own IN step; an
        // IN ticket's is whichever predecessor(s) sit at the previous seq
        // (the slowest one, when there's more than one running in
        // parallel, since that's the one actually limiting the cap). null
        // for the very first step in the route — there's nothing upstream,
        // it's sourced directly from the bundle itself.
        $entrySources = $ledger['entrySourceBySeq'][$seq] ?? [];
        $previousStepFor = function (string $dir) use ($rom, $currentWoo, $ticketFor, $scanned, $entrySources) {
            if ($dir === 'OUT' && $rom->in) {
                return [
                    'operation_code' => optional($rom->operation)->operation_code,
                    'operation_description' => optional($rom->operation)->description,
                    'direction' => 'IN',
                    'released' => $scanned($ticketFor($currentWoo->id, 'IN')),
                    'same_operation' => true,
                ];
            }
            if (empty($entrySources)) {
                return null;
            }
            $bottleneck = collect($entrySources)->sortBy('released')->first();
            return $bottleneck + ['same_operation' => false];
        };
        $formatStep = function (?array $step) {
            if ($step === null) {
                return 'the previous step';
            }
            $label = $step['operation_description'] ?: $step['operation_code'];
            return "{$label} ({$step['operation_code']}) {$step['direction']}";
        };

        // Resolve a single direction's ticket + how much is available to
        // scan against it right now. Returns one of:
        //  ['ticket' => ..., 'remaining' => ...]  — ready to scan
        //  ['blocked' => int]                     — has its own room left, but upstream hasn't released enough yet
        //  ['exhausted' => true]                  — this direction's own capacity is fully accounted for
        //  ['missing' => true]                    — no ticket generated for it yet
        $resolveDirection = function (string $dir) use ($currentWoo, $rom, $ticketFor, $ticketRemainingFn, $scanned, $rejected, $outstandingRework, $entryCap, $capped) {
            $ticket = $ticketFor($currentWoo->id, $dir);
            if (!$ticket) {
                return ['missing' => true];
            }

            $remaining = $ticketRemainingFn($ticket);
            if ($remaining <= 0) {
                return ['exhausted' => true];
            }

            $cap = $dir === 'IN'
                ? $entryCap
                : ($rom->in ? $scanned($ticketFor($currentWoo->id, 'IN')) : $entryCap);
            $consumed = $scanned($ticket) + $rejected($ticket) + $outstandingRework($ticket);
            $available = $capped($remaining, $cap === null ? null : $cap - $consumed);

            return $available > 0
                ? ['ticket' => $ticket, 'remaining' => $available]
                : ['blocked' => $cap ?? 0];
        };

        if ($requestedDirection !== null) {
            if (!$availableDirections[strtolower($requestedDirection)]) {
                return ['error' => $this->error("This operation has no {$requestedDirection} step.", ['code' => 'NO_SUCH_DIRECTION'], 422)];
            }
            $directionsToTry = [$requestedDirection];
        } else {
            $directionsToTry = array_values(array_filter(['IN', 'OUT'], fn ($d) => $availableDirections[strtolower($d)]));
        }

        $direction = null;
        $ticket = null;
        $remaining = 0;
        $blockedOnUpstream = null;
        $blockedDirection = null;

        foreach ($directionsToTry as $dir) {
            $result = $resolveDirection($dir);

            if (isset($result['missing'])) {
                return ['error' => $this->error('No bundle ticket has been generated yet for this operation.', ['code' => 'TICKET_NOT_GENERATED'], 422)];
            }
            if (isset($result['ticket'])) {
                $ticket = $result['ticket'];
                $direction = $dir;
                $remaining = $result['remaining'];
                break;
            }
            if (isset($result['blocked'])) {
                // This direction still has theoretical room but upstream
                // hasn't released enough for MORE of it yet — that must not
                // stop OUT from being offered for whatever already came IN.
                // E.g. IN took 12 of 20, the other 8 is stuck in rework: OUT
                // should still be scannable for the 12 that are physically
                // on hand right now — the factory floor doesn't wait on the
                // 8 to keep the 12 moving. Only remember the first blocked
                // reason, in case nothing else pans out either.
                if ($blockedOnUpstream === null) {
                    $blockedOnUpstream = $result['blocked'];
                    $blockedDirection = $dir;
                }
                continue;
            }
            // 'exhausted': this direction is fully accounted for on its own
            // terms — move on to the next requested direction, if any.
        }

        if (!$ticket) {
            if ($blockedOnUpstream !== null) {
                $source = $previousStepFor($blockedDirection);
                $sourceLabel = $formatStep($source);
                return ['error' => $this->error(
                    "Nothing available yet — {$sourceLabel} has only released {$blockedOnUpstream} so far.",
                    ['code' => 'WAITING_ON_UPSTREAM', 'previous_step' => $source],
                    422
                )];
            }

            $message = $requestedDirection !== null
                ? "This operation's {$requestedDirection} step has already been fully scanned for this bundle."
                : 'This operation has already been fully scanned for this bundle.';
            return ['error' => $this->error($message, ['code' => 'ALREADY_SCANNED'], 422)];
        }

        return [
            'bundle' => $bundle,
            'ticket' => $ticket,
            'direction' => $direction,
            'remaining' => $remaining,
            'rom' => $rom,
            'ticketRemaining' => $ticketRemainingMap,
            'progress' => $progress,
            'availableDirections' => $availableDirections,
            'previousStep' => $previousStepFor($direction),
        ];
    }

    /**
     * Undo a good-quantity scan entry. Only the scanning user, only within a
     * short window — soft-deletes rather than hard-deletes to preserve the
     * audit trail.
     */
    public function undoSecondaryScan(int $id): JsonResponse
    {
        try {
            $entry = BundleTicketSecondary::find($id);
            if (!$entry) {
                return $this->error('Scan not found.', ['code' => 'UNKNOWN_SCAN'], 404);
            }

            if ((int) $entry->created_by !== (int) Auth::id()) {
                return $this->error('You can only undo your own scans.', ['code' => 'NOT_YOUR_SCAN'], 403);
            }

            if ($entry->created_at && $entry->created_at->lt(now()->subMinutes(5))) {
                return $this->error('This scan can no longer be undone.', ['code' => 'EDIT_WINDOW_EXPIRED'], 422);
            }

            $entry->active = false;
            $entry->save();

            return $this->success('Scan undone.');
        } catch (Exception $e) {
            return $this->error('Failed to undo scan.', $e->getMessage(), 500);
        }
    }

    /**
     * Undo a reject entry. Same rules as undoSecondaryScan().
     */
    public function undoRejectScan(int $id): JsonResponse
    {
        try {
            $entry = BundleTicketReject::find($id);
            if (!$entry) {
                return $this->error('Reject not found.', ['code' => 'UNKNOWN_REJECT'], 404);
            }

            if ((int) $entry->created_by !== (int) Auth::id()) {
                return $this->error('You can only undo your own rejects.', ['code' => 'NOT_YOUR_SCAN'], 403);
            }

            if ($entry->created_at && $entry->created_at->lt(now()->subMinutes(5))) {
                return $this->error('This reject can no longer be undone.', ['code' => 'EDIT_WINDOW_EXPIRED'], 422);
            }

            $entry->active = false;
            $entry->save();

            return $this->success('Reject undone.');
        } catch (Exception $e) {
            return $this->error('Failed to undo reject.', $e->getMessage(), 500);
        }
    }

    /**
     * Undo a "sent to rework" entry. Same rules as undoSecondaryScan(), plus
     * it must not have been partially resolved already (undoing it can't be
     * allowed to push the ticket's outstanding-rework total negative).
     */
    public function undoReworkSend(int $id): JsonResponse
    {
        try {
            $entry = BundleTicketRework::find($id);
            if (!$entry) {
                return $this->error('Rework entry not found.', ['code' => 'UNKNOWN_REWORK'], 404);
            }

            if ((int) $entry->created_by !== (int) Auth::id()) {
                return $this->error('You can only undo your own entries.', ['code' => 'NOT_YOUR_SCAN'], 403);
            }

            if ($entry->created_at && $entry->created_at->lt(now()->subMinutes(5))) {
                return $this->error('This entry can no longer be undone.', ['code' => 'EDIT_WINDOW_EXPIRED'], 422);
            }

            $sent = (int) BundleTicketRework::where('bundle_ticket_id', $entry->bundle_ticket_id)
                ->where('active', true)
                ->sum('rework_qty');
            $returned = (int) BundleTicketReworkReturn::where('bundle_ticket_id', $entry->bundle_ticket_id)
                ->where('active', true)
                ->selectRaw('COALESCE(SUM(return_qty + reject_qty), 0) as total')
                ->value('total');

            if (($sent - $entry->rework_qty) < $returned) {
                return $this->error('Part of this batch has already been returned from rework — it can no longer be undone.', ['code' => 'ALREADY_RESOLVED'], 422);
            }

            $entry->active = false;
            $entry->save();

            return $this->success('Rework entry undone.');
        } catch (Exception $e) {
            return $this->error('Failed to undo rework entry.', $e->getMessage(), 500);
        }
    }

    /**
     * Undo a rework return. Deactivates the return row plus whichever
     * derived scan/reject rows it created, restoring the outstanding qty.
     */
    public function undoReworkReturn(int $id): JsonResponse
    {
        try {
            $entry = BundleTicketReworkReturn::find($id);
            if (!$entry) {
                return $this->error('Rework return not found.', ['code' => 'UNKNOWN_REWORK_RETURN'], 404);
            }

            if ((int) $entry->created_by !== (int) Auth::id()) {
                return $this->error('You can only undo your own entries.', ['code' => 'NOT_YOUR_SCAN'], 403);
            }

            if ($entry->created_at && $entry->created_at->lt(now()->subMinutes(5))) {
                return $this->error('This entry can no longer be undone.', ['code' => 'EDIT_WINDOW_EXPIRED'], 422);
            }

            DB::transaction(function () use ($entry) {
                if ($entry->bundle_ticket_secondary_id) {
                    BundleTicketSecondary::where('id', $entry->bundle_ticket_secondary_id)->update(['active' => false]);
                }
                if ($entry->bundle_ticket_reject_id) {
                    BundleTicketReject::where('id', $entry->bundle_ticket_reject_id)->update(['active' => false]);
                }
                $entry->active = false;
                $entry->save();
            });

            return $this->success('Rework return undone.');
        } catch (Exception $e) {
            return $this->error('Failed to undo rework return.', $e->getMessage(), 500);
        }
    }

    /**
     * The authenticated user's most recent scan + reject entries, merged and
     * pre-joined for display.
     */
    public function recentScans(Request $request): JsonResponse
    {
        try {
            $withChain = 'bundleTicket.workOrderOperation.routingOperationMaster.operation';
            $teamId = $request->query('daily_shift_team_id');

            $secondaries = BundleTicketSecondary::with([$withChain, 'dailyShiftTeam.team'])
                ->where('created_by', Auth::id())
                ->where('active', true)
                ->when($teamId, fn ($query) => $query->where('daily_shift_team_id', $teamId))
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(fn (BundleTicketSecondary $entry) => $this->formatRecentScan($entry, $entry->source === 'REWORK_RETURN' ? 'REWORK_RETURN' : 'SCAN', $entry->scan_qty, null));

            $rejects = BundleTicketReject::with([$withChain, 'dailyShiftTeam.team'])
                ->where('created_by', Auth::id())
                ->where('active', true)
                ->when($teamId, fn ($query) => $query->where('daily_shift_team_id', $teamId))
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(fn (BundleTicketReject $entry) => $this->formatRecentScan($entry, 'REJECT', $entry->reject_qty, $entry->reject_reason));

            $reworks = BundleTicketRework::with([$withChain, 'dailyShiftTeam.team'])
                ->where('created_by', Auth::id())
                ->where('active', true)
                ->when($teamId, fn ($query) => $query->where('daily_shift_team_id', $teamId))
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(fn (BundleTicketRework $entry) => $this->formatRecentScan($entry, 'REWORK', $entry->rework_qty, null));

            $recent = $secondaries->concat($rejects)->concat($reworks)
                ->sortByDesc('scanned_at')
                ->values()
                ->take(20);

            return $this->success('Recent scans retrieved successfully.', $recent);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve recent scans.', $e->getMessage(), 500);
        }
    }

    /**
     * Flatten a BundleTicketSecondary/BundleTicketReject/BundleTicketRework
     * row into the shape the Recent Scans panel displays.
     *
     * @param BundleTicketSecondary|BundleTicketReject|BundleTicketRework $entry
     */
    private function formatRecentScan($entry, string $type, int $qty, ?string $rejectReason): array
    {
        $ticket = $entry->bundleTicket;
        $operation = optional(optional(optional($ticket)->workOrderOperation)->routingOperationMaster)->operation;

        return [
            'id' => $entry->id,
            'type' => $type,
            'bundle_ticket_id' => optional($ticket)->id,
            'bundle_id' => optional($ticket)->bundle_id,
            'direction' => optional($ticket)->direction,
            'qty' => $qty,
            'reject_reason' => $rejectReason,
            'operation_code' => $operation->operation_code ?? null,
            'operation_description' => $operation->description ?? null,
            'team_name' => optional(optional($entry->dailyShiftTeam)->team)->team_name,
            'scanned_at' => $entry->created_at,
        ];
    }

    /**
     * Extract a bundle id from scanned QR/barcode text. Tickets are assumed
     * to encode the bundle's numeric id, optionally with a text prefix
     * (e.g. "BND-000123").
     */
    private function parseBundleId(?string $ticketCode): ?int
    {
        if ($ticketCode === null) {
            return null;
        }

        if (preg_match('/(\d+)\s*$/', trim($ticketCode), $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Build a consistent success JSON response.
     *
     * @param string $message
     * @param mixed $data
     * @param int $status
     * @return JsonResponse
     */
    private function success(string $message, $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Build a consistent error JSON response.
     *
     * @param string $message
     * @param mixed $errors
     * @param int $status
     * @return JsonResponse
     */
    private function error(string $message, $errors = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
