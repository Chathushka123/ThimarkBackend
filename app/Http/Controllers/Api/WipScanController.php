<?php

namespace App\Http\Controllers\Api;

use App\DailyShiftTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\LookupBundleTicketRequest;
use App\Http\Requests\ScanBundleTicketRequest;
use App\Models\Bundle;
use App\Models\BundleTicket;
use App\Models\BundleTicketReject;
use App\Models\BundleTicketSecondary;
use App\Models\UserOperation;
use App\Models\WorkOrderOperation;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Handles the Production WIP Scanning screen: a line operator scans a
 * bundle's QR/barcode at their assigned Operation, on behalf of their
 * currently active shift team. Bundle tickets (one per bundle x route step
 * x direction) are pre-generated elsewhere with a planned qty; scanning
 * records actual quantity against bundle_ticket_secondaries (good) and
 * bundle_ticket_rejects (defective), enforcing route sequence and that the
 * two never sum past the ticket's planned qty.
 *
 * A scan is a two-step flow: lookup() resolves the target ticket and its
 * remaining qty for the operator to review/amend, then scan() performs the
 * actual write once they confirm (optionally with a corrected qty and/or a
 * reject split). Both share resolveScanTarget() for the validation/lookup
 * logic so the two can never drift apart.
 */
class WipScanController extends Controller
{
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
     * start/end window). There's no user-to-team link in the schema, so
     * every operator sees the same list and picks their own team, the same
     * way they pick their own operation.
     */
    public function myTeams(): JsonResponse
    {
        try {
            $now = now();

            $teams = DailyShiftTeam::with(['team', 'dailyShift.shift'])
                ->where('active', true)
                ->where('start_date_time', '<=', $now)
                ->where('end_date_time', '>=', $now)
                ->orderBy('start_date_time')
                ->get()
                ->map(function (DailyShiftTeam $dst) {
                    return [
                        'id' => $dst->id,
                        'team_code' => optional($dst->team)->team_code,
                        'team_name' => optional($dst->team)->team_name,
                        'shift_date' => optional($dst->dailyShift)->shift_date,
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
            $target = $this->resolveScanTarget($operationId, $bundleId, false);
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
            ]);
        } catch (Exception $e) {
            return $this->error('Failed to look up bundle.', $e->getMessage(), 500);
        }
    }

    /**
     * Record a scan against the bundle ticket for the given operation,
     * auto-detecting IN vs OUT and enforcing that earlier route steps are
     * already fully accounted for (scanned + rejected).
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
                // ticket unsafely.
                $target = $this->resolveScanTarget($operationId, $bundleId, true);
                if (isset($target['error'])) {
                    return $target['error'];
                }

                $ticket = $target['ticket'];
                $rom = $target['rom'];
                $direction = $target['direction'];
                $remaining = $target['remaining'];
                $workOrderOperations = $target['workOrderOperations'];
                $isComplete = $target['isComplete'];

                $scanQty = $request->filled('scan_qty') ? (int) $request->input('scan_qty') : null;
                $rejectQty = (int) $request->input('reject_qty', 0);

                if ($scanQty === null) {
                    // Fast single-tap path: scan everything not already spoken
                    // for by the reject qty on this same request.
                    $scanQty = max($remaining - $rejectQty, 0);
                }

                if ($scanQty < 0 || $rejectQty < 0) {
                    return $this->error('Quantities cannot be negative.', ['code' => 'INVALID_QTY'], 422);
                }

                if ($scanQty + $rejectQty <= 0) {
                    return $this->error('Enter a scanned or rejected quantity.', ['code' => 'INVALID_QTY'], 422);
                }

                if ($scanQty + $rejectQty > $remaining) {
                    return $this->error("Only {$remaining} left on this ticket.", ['code' => 'QTY_EXCEEDS_TICKET'], 422);
                }

                if ($scanQty > 0) {
                    BundleTicketSecondary::create([
                        'bundle_ticket_id' => $ticket->id,
                        'scan_qty' => $scanQty,
                        'daily_shift_team_id' => $dailyShiftTeamId,
                        'active' => true,
                    ]);
                }

                if ($rejectQty > 0) {
                    BundleTicketReject::create([
                        'bundle_ticket_id' => $ticket->id,
                        'reject_qty' => $rejectQty,
                        'reject_reason' => $request->input('reject_reason'),
                        'daily_shift_team_id' => $dailyShiftTeamId,
                        'active' => true,
                    ]);
                }

                // Reflect the just-recorded quantities so progress/completion
                // below is computed against the post-scan state. isComplete()
                // closes over these same Collection instances, so mutating
                // them here (rather than reassigning $target['...']) is what
                // makes the recompute below see this scan.
                $target['scannedTotals'][$ticket->id] = (int) ($target['scannedTotals'][$ticket->id] ?? 0) + $scanQty;
                $target['rejectedTotals'][$ticket->id] = (int) ($target['rejectedTotals'][$ticket->id] ?? 0) + $rejectQty;

                $remainingAfter = $ticket->qty - ((int) $target['scannedTotals'][$ticket->id] + (int) $target['rejectedTotals'][$ticket->id]);
                $completed = $workOrderOperations->filter($isComplete)->count();
                $total = $workOrderOperations->count();

                return $this->success('Scan recorded.', [
                    'bundle_id' => $target['bundle']->id,
                    'work_order_id' => $target['bundle']->work_order_id,
                    'bundle_ticket_id' => $ticket->id,
                    'direction' => $direction,
                    'scan_qty' => $scanQty,
                    'reject_qty' => $rejectQty,
                    'remaining_after' => $remainingAfter,
                    'ticket_complete' => $remainingAfter <= 0,
                    'operation_code' => $rom->operation->operation_code ?? null,
                    'operation_description' => $rom->operation->description ?? null,
                    'seq' => $rom->seq,
                    'progress' => ['completed' => $completed, 'total' => $total],
                    'bundle_complete' => $completed === $total,
                ], 201);
            });
        } catch (Exception $e) {
            return $this->error('Failed to record scan.', $e->getMessage(), 500);
        }
    }

    /**
     * Resolve the target bundle ticket for a scan/lookup: bundle lookup,
     * route-sequence validation, and IN/OUT ticket resolution. Shared by
     * lookup() (read-only) and scan() (which locks rows for the write).
     *
     * @return array{error: JsonResponse}|array{bundle: Bundle, workOrderOperations: \Illuminate\Support\Collection, currentWoo: WorkOrderOperation, rom: mixed, ticket: BundleTicket, direction: string, remaining: int, isComplete: \Closure}
     */
    private function resolveScanTarget(int $operationId, int $bundleId, bool $forWrite): array
    {
        $bundleQuery = Bundle::where('id', $bundleId)->where('active', true);
        if ($forWrite) {
            $bundleQuery->lockForUpdate();
        }
        $bundle = $bundleQuery->first();
        if (!$bundle) {
            return ['error' => $this->error('Bundle not found.', ['code' => 'UNKNOWN_BUNDLE'], 404)];
        }

        $workOrderOperations = WorkOrderOperation::with('routingOperationMaster.operation')
            ->where('work_order_id', $bundle->work_order_id)
            ->where('active', true)
            ->get();

        $currentWoo = $workOrderOperations->first(
            fn ($woo) => $woo->routingOperationMaster && (int) $woo->routingOperationMaster->operation_id === $operationId
        );

        if (!$currentWoo) {
            return ['error' => $this->error("This operation isn't part of the bundle's route.", ['code' => 'NO_MATCHING_OPERATION'], 422)];
        }

        // Pre-generated tickets (one per work_order_operation x direction) —
        // scanning never creates these, it only records quantity against them.
        $ticketsQuery = BundleTicket::where('bundle_id', $bundle->id)
            ->whereIn('work_order_operation_id', $workOrderOperations->pluck('id'))
            ->where('active', true);
        if ($forWrite) {
            $ticketsQuery->lockForUpdate();
        }
        $tickets = $ticketsQuery->get();

        $ticketsByWooDirection = $tickets->groupBy(fn ($t) => $t->work_order_operation_id . ':' . $t->direction);
        $ticketFor = fn ($wooId, $direction) => $ticketsByWooDirection->get("{$wooId}:{$direction}")?->first();

        $ticketIds = $tickets->pluck('id');
        $scannedTotals = BundleTicketSecondary::whereIn('bundle_ticket_id', $ticketIds)
            ->where('active', true)
            ->groupBy('bundle_ticket_id')
            ->selectRaw('bundle_ticket_id, SUM(scan_qty) as total')
            ->pluck('total', 'bundle_ticket_id');
        $rejectedTotals = BundleTicketReject::whereIn('bundle_ticket_id', $ticketIds)
            ->where('active', true)
            ->groupBy('bundle_ticket_id')
            ->selectRaw('bundle_ticket_id, SUM(reject_qty) as total')
            ->pluck('total', 'bundle_ticket_id');

        $fulfilledQty = fn (BundleTicket $ticket) => (int) ($scannedTotals[$ticket->id] ?? 0) + (int) ($rejectedTotals[$ticket->id] ?? 0);
        $isFulfilled = fn (BundleTicket $ticket) => $fulfilledQty($ticket) >= $ticket->qty;

        // A step is "complete" only once every direction its route requires
        // has a generated ticket AND that ticket is fulfilled.
        $isComplete = function ($woo) use ($ticketFor, $isFulfilled) {
            $rom = $woo->routingOperationMaster;
            if ($rom->in) {
                $ticket = $ticketFor($woo->id, 'IN');
                if (!$ticket || !$isFulfilled($ticket)) {
                    return false;
                }
            }
            if ($rom->out) {
                $ticket = $ticketFor($woo->id, 'OUT');
                if (!$ticket || !$isFulfilled($ticket)) {
                    return false;
                }
            }
            return true;
        };

        $pendingSeqs = $workOrderOperations->reject($isComplete)->pluck('routingOperationMaster.seq');

        if ($pendingSeqs->isEmpty()) {
            return ['error' => $this->error('This bundle has already completed its full route.', ['code' => 'BUNDLE_ALREADY_COMPLETE'], 422)];
        }

        $minPendingSeq = (int) $pendingSeqs->min();
        $currentSeq = (int) $currentWoo->routingOperationMaster->seq;

        if ($currentSeq !== $minPendingSeq) {
            $pendingNames = $workOrderOperations
                ->filter(fn ($woo) => (int) $woo->routingOperationMaster->seq === $minPendingSeq)
                ->map(fn ($woo) => $woo->routingOperationMaster->operation->description ?? $woo->routingOperationMaster->operation->operation_code)
                ->unique()
                ->implode(', ');

            return ['error' => $this->error("Out of sequence — pending: {$pendingNames}.", ['code' => 'OUT_OF_SEQUENCE'], 422)];
        }

        if ($isComplete($currentWoo)) {
            return ['error' => $this->error('This operation has already been fully scanned for this bundle.', ['code' => 'ALREADY_SCANNED'], 422)];
        }

        $rom = $currentWoo->routingOperationMaster;
        $direction = null;
        $ticket = null;

        if ($rom->in) {
            $inTicket = $ticketFor($currentWoo->id, 'IN');
            if (!$inTicket) {
                return ['error' => $this->error('No bundle ticket has been generated yet for this operation.', ['code' => 'TICKET_NOT_GENERATED'], 422)];
            }
            if (!$isFulfilled($inTicket)) {
                $ticket = $inTicket;
                $direction = 'IN';
            }
        }

        if (!$ticket && $rom->out) {
            $outTicket = $ticketFor($currentWoo->id, 'OUT');
            if (!$outTicket) {
                return ['error' => $this->error('No bundle ticket has been generated yet for this operation.', ['code' => 'TICKET_NOT_GENERATED'], 422)];
            }
            if (!$isFulfilled($outTicket)) {
                $ticket = $outTicket;
                $direction = 'OUT';
            }
        }

        if (!$ticket) {
            return ['error' => $this->error('This operation has already been fully scanned for this bundle.', ['code' => 'ALREADY_SCANNED'], 422)];
        }

        $remaining = $ticket->qty - $fulfilledQty($ticket);

        return [
            'bundle' => $bundle,
            'workOrderOperations' => $workOrderOperations,
            'currentWoo' => $currentWoo,
            'rom' => $rom,
            'ticket' => $ticket,
            'direction' => $direction,
            'remaining' => $remaining,
            'isComplete' => $isComplete,
            // Returned (rather than kept local) so scan() can mutate them
            // in place after writing — isComplete()/fulfilledQty() close
            // over these same Collection instances, so mutating an entry
            // here is what makes the post-write completed/total below
            // reflect the scan that was just recorded.
            'scannedTotals' => $scannedTotals,
            'rejectedTotals' => $rejectedTotals,
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
     * The authenticated user's most recent scan + reject entries, merged and
     * pre-joined for display.
     */
    public function recentScans(): JsonResponse
    {
        try {
            $withChain = 'bundleTicket.workOrderOperation.routingOperationMaster.operation';

            $secondaries = BundleTicketSecondary::with([$withChain, 'dailyShiftTeam.team'])
                ->where('created_by', Auth::id())
                ->where('active', true)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(fn (BundleTicketSecondary $entry) => $this->formatRecentScan($entry, 'SCAN', $entry->scan_qty, null));

            $rejects = BundleTicketReject::with([$withChain, 'dailyShiftTeam.team'])
                ->where('created_by', Auth::id())
                ->where('active', true)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(fn (BundleTicketReject $entry) => $this->formatRecentScan($entry, 'REJECT', $entry->reject_qty, $entry->reject_reason));

            $recent = $secondaries->concat($rejects)
                ->sortByDesc('scanned_at')
                ->values()
                ->take(20);

            return $this->success('Recent scans retrieved successfully.', $recent);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve recent scans.', $e->getMessage(), 500);
        }
    }

    /**
     * Flatten a BundleTicketSecondary/BundleTicketReject row into the shape
     * the Recent Scans panel displays.
     *
     * @param BundleTicketSecondary|BundleTicketReject $entry
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
