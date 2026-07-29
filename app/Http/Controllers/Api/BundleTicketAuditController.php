<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BundleTicket;
use App\Models\BundleTicketReject;
use App\Models\BundleTicketRework;
use App\Models\BundleTicketReworkReturn;
use App\Models\BundleTicketSecondary;
use App\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin cleanup tool for the Production WIP Scanning screen: lets an admin
 * browse every scan event (good scans, rejects, rework sends, rework
 * returns) recorded in a date range, grouped main-model -> model -> batch ->
 * work order -> bundle -> bundle ticket, and delete individual events.
 *
 * Deletion rule, based on the bundle's real routing sequence (routing
 * operation master `seq`) rather than on timestamps or raw ticket ids:
 *
 * - A ticket is at its bundle's FRONTIER — eligible to have events deleted
 *   — only if nothing "after" it has any active event. "After" means: a
 *   strictly greater seq on ANY operation of the bundle, or (within the
 *   very same operation) its own OUT ticket, when checking the IN ticket.
 * - Operations sharing the same seq are PARALLEL stations (e.g. "Left
 *   Grinding" and "Right Grinding" both at seq 2) — they never block each
 *   other. Both can be at the frontier, and both can be deleted,
 *   independently of one another.
 * - Within one operation, scanning happens IN then OUT — OUT is always
 *   "later" than that same operation's IN, so an OUT with activity blocks
 *   deleting its own IN, but an IN having activity never blocks its OUT.
 *
 * This is safe because a ticket that's genuinely later in the route can
 * only have activity once everything it depends on already happened, so
 * clearing frontier tickets first and working backward can never strand a
 * downstream record pointing at qty that no longer exists.
 */
class BundleTicketAuditController extends Controller
{
    private const EVENT_MODELS = [
        'secondary' => BundleTicketSecondary::class,
        'reject' => BundleTicketReject::class,
        'rework' => BundleTicketRework::class,
        'rework_return' => BundleTicketReworkReturn::class,
    ];

    /**
     * Every scan event created within [from, to], grouped into one row per
     * bundle ticket with main-model/model/batch/work-order/bundle context
     * attached, ready for the frontend to fold into a collapsible tree.
     */
    public function hierarchy(Request $request): JsonResponse
    {
        try {
            $from = $request->query('from')
                ? Carbon::parse($request->query('from'))->startOfDay()
                : now()->subDay()->startOfDay();
            $to = $request->query('to')
                ? Carbon::parse($request->query('to'))->endOfDay()
                : now()->endOfDay();

            // active=true only — an already-deleted event must disappear from
            // this list entirely, not linger looking exactly like a live one.
            // (The deletion-eligibility checks below already filter this way
            // on their own; this makes the display agree with them.)
            $secondaries = BundleTicketSecondary::with('dailyShiftTeam.team')
                ->where('active', true)
                ->whereBetween('created_at', [$from, $to])->get();
            $rejects = BundleTicketReject::with(['dailyShiftTeam.team', 'reason'])
                ->where('active', true)
                ->whereBetween('created_at', [$from, $to])->get();
            $reworks = BundleTicketRework::with(['dailyShiftTeam.team', 'reason'])
                ->where('active', true)
                ->whereBetween('created_at', [$from, $to])->get();
            $reworkReturns = BundleTicketReworkReturn::with(['dailyShiftTeam.team', 'reason'])
                ->where('active', true)
                ->whereBetween('created_at', [$from, $to])->get();

            $ticketIds = collect()
                ->merge($secondaries->pluck('bundle_ticket_id'))
                ->merge($rejects->pluck('bundle_ticket_id'))
                ->merge($reworks->pluck('bundle_ticket_id'))
                ->merge($reworkReturns->pluck('bundle_ticket_id'))
                ->unique()
                ->values();

            if ($ticketIds->isEmpty()) {
                return $this->success('Scan history retrieved successfully.', []);
            }

            $tickets = BundleTicket::with([
                'workOrderOperation.routingOperationMaster.operation',
                'bundle.workOrder.batchDetail.batch',
                'bundle.workOrder.batchDetail.model.mainModel',
            ])->whereIn('id', $ticketIds)->get()->keyBy('id');

            // Per-ticket "is this deletable" check, resolved from the FULL
            // route/history (not just what's in [from, to]) — a later
            // event outside the visible range still has to block deletion,
            // and the frontier comparison needs every ticket on the route,
            // not just the ones with events in this window.
            $ticketEligibility = [];
            foreach ($tickets->pluck('bundle_id')->unique() as $bundleId) {
                [$bundleTickets, $activeMap] = $this->loadBundleTicketActivity($bundleId);
                foreach ($bundleTickets as $bt) {
                    if (!$activeMap[$bt->id]) {
                        continue;
                    }
                    $isFrontier = $this->isTicketAtFrontier($bt, $bundleTickets, $activeMap);
                    $ticketEligibility[$bt->id] = [
                        'isFrontier' => $isFrontier,
                        'latest' => $isFrontier ? $this->latestEventForTicket($bt->id) : null,
                    ];
                }
            }

            $userIds = collect()
                ->merge($secondaries->pluck('created_by'))
                ->merge($rejects->pluck('created_by'))
                ->merge($reworks->pluck('created_by'))
                ->merge($reworkReturns->pluck('created_by'))
                ->filter()
                ->unique()
                ->values();
            $userEmails = User::whereIn('id', $userIds)->pluck('email', 'id');

            $eventsByTicket = [];
            foreach ($secondaries as $e) {
                $eventsByTicket[$e->bundle_ticket_id][] = $this->formatEvent($e, 'SECONDARY', $userEmails, $ticketEligibility);
            }
            foreach ($rejects as $e) {
                $eventsByTicket[$e->bundle_ticket_id][] = $this->formatEvent($e, 'REJECT', $userEmails, $ticketEligibility);
            }
            foreach ($reworks as $e) {
                $eventsByTicket[$e->bundle_ticket_id][] = $this->formatEvent($e, 'REWORK', $userEmails, $ticketEligibility);
            }
            foreach ($reworkReturns as $e) {
                $eventsByTicket[$e->bundle_ticket_id][] = $this->formatEvent($e, 'REWORK_RETURN', $userEmails, $ticketEligibility);
            }

            $groups = [];
            foreach ($ticketIds as $ticketId) {
                $ticket = $tickets->get($ticketId);
                if (!$ticket) {
                    continue;
                }

                $bundle = $ticket->bundle;
                $workOrder = optional($bundle)->workOrder;
                $batchDetail = optional($workOrder)->batchDetail;
                $batch = optional($batchDetail)->batch;
                $model = optional($batchDetail)->model;
                $mainModel = optional($model)->mainModel;
                $rom = optional(optional($ticket->workOrderOperation)->routingOperationMaster);

                $events = $eventsByTicket[$ticketId] ?? [];
                usort($events, function ($a, $b) {
                    $cmp = strcmp($b['created_at'], $a['created_at']);
                    return $cmp !== 0 ? $cmp : ($b['id'] <=> $a['id']);
                });

                $groups[] = [
                    'main_model_id' => optional($mainModel)->id,
                    'main_model_name' => optional($mainModel)->name ?: 'Unassigned',
                    'model_id' => optional($model)->id,
                    'model_name' => optional($model)->name ?: 'Unassigned',
                    'batch_id' => optional($batch)->id,
                    'batch_no' => optional($batch)->batch_no ?: 'Unassigned',
                    'work_order_id' => optional($workOrder)->id,
                    'bundle_id' => optional($bundle)->id,
                    'bundle_qty' => optional($bundle)->qty,
                    'bundle_size' => optional($bundle)->size,
                    'bundle_ticket_id' => $ticket->id,
                    'operation_code' => optional($rom->operation)->operation_code,
                    'operation_description' => optional($rom->operation)->description,
                    'seq' => $rom->seq,
                    'direction' => $ticket->direction,
                    'ticket_qty' => $ticket->qty,
                    'events' => $events,
                ];
            }

            return $this->success('Scan history retrieved successfully.', $groups);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve scan history.', $e->getMessage(), 500);
        }
    }

    /**
     * Delete one scan event, only if it's still the most recent active scan
     * event anywhere on its bundle.
     */
    public function deleteScanEvent(string $type, int $id): JsonResponse
    {
        $typeKey = strtolower($type);
        if (!isset(self::EVENT_MODELS[$typeKey])) {
            return $this->error('Unknown scan event type.', ['code' => 'UNKNOWN_EVENT_TYPE'], 422);
        }

        $modelClass = self::EVENT_MODELS[$typeKey];
        $entry = $modelClass::where('active', true)->find($id);
        if (!$entry) {
            return $this->error('Scan event not found — it may already be deleted.', ['code' => 'UNKNOWN_EVENT'], 404);
        }

        $ticket = BundleTicket::find($entry->bundle_ticket_id);
        if (!$ticket) {
            return $this->error('Bundle ticket not found.', ['code' => 'UNKNOWN_TICKET'], 404);
        }

        try {
            $typeCode = strtoupper($typeKey);

            [$bundleTickets, $activeMap] = $this->loadBundleTicketActivity($ticket->bundle_id);
            $blocker = $this->findBlockingTicket($ticket, $bundleTickets, $activeMap);
            if ($blocker) {
                return $this->error(
                    $this->describeBlocker($blocker),
                    ['code' => 'NOT_FRONTIER_TICKET', 'blocking_ticket_id' => $blocker->id],
                    422
                );
            }

            $latest = $this->latestEventForTicket($ticket->id);
            $isLatest = $latest && $latest['type'] === $typeCode && (int) $latest['id'] === $entry->id;

            if (!$isLatest) {
                return $this->error(
                    "This isn't the most recent scan recorded on this ticket. Delete newer entries on it first, working backward from the latest.",
                    ['code' => 'NOT_LATEST_EVENT', 'blocking_event' => $latest],
                    422
                );
            }

            DB::transaction(function () use ($entry, $typeKey) {
                // Deleting a rework return also undoes the good/reject rows it
                // spawned — same cascade WipScanController::undoReworkReturn()
                // already applies to the operator-facing 5-minute undo.
                if ($typeKey === 'rework_return') {
                    if ($entry->bundle_ticket_secondary_id) {
                        BundleTicketSecondary::where('id', $entry->bundle_ticket_secondary_id)
                            ->update(['active' => false, 'updated_by' => Auth::id()]);
                    }
                    if ($entry->bundle_ticket_reject_id) {
                        BundleTicketReject::where('id', $entry->bundle_ticket_reject_id)
                            ->update(['active' => false, 'updated_by' => Auth::id()]);
                    }
                }
                $entry->active = false;
                $entry->updated_by = Auth::id();
                $entry->save();
            });

            return $this->success('Scan event deleted successfully.', ['id' => $entry->id, 'type' => $typeCode]);
        } catch (Exception $e) {
            return $this->error('Failed to delete scan event.', $e->getMessage(), 500);
        }
    }

    /**
     * Delete every active scan event (secondary, reject, rework, rework
     * return) recorded against one bundle ticket in a single action — the
     * bulk equivalent of repeatedly deleting that ticket's latest event
     * until none remain. Only allowed while the ticket is still its
     * bundle's frontier (see the class docblock and findBlockingTicket()):
     * clearing a whole ticket at once is never less safe than doing it one
     * event at a time, since by definition nothing downstream of the
     * frontier ticket has happened yet.
     */
    public function deleteTicketEvents(int $bundleTicketId): JsonResponse
    {
        $ticket = BundleTicket::with('workOrderOperation.routingOperationMaster')->find($bundleTicketId);
        if (!$ticket) {
            return $this->error('Bundle ticket not found.', ['code' => 'UNKNOWN_TICKET'], 404);
        }

        try {
            if (!$this->ticketHasActiveEvent($bundleTicketId)) {
                return $this->error('There are no active scan events on this ticket to delete.', ['code' => 'NO_EVENTS'], 422);
            }

            [$bundleTickets, $activeMap] = $this->loadBundleTicketActivity($ticket->bundle_id);
            $blocker = $this->findBlockingTicket($ticket, $bundleTickets, $activeMap);
            if ($blocker) {
                return $this->error(
                    $this->describeBlocker($blocker),
                    ['code' => 'NOT_FRONTIER_TICKET', 'blocking_ticket_id' => $blocker->id],
                    422
                );
            }

            $deletedCounts = DB::transaction(function () use ($bundleTicketId) {
                $counts = [
                    'secondary' => BundleTicketSecondary::where('bundle_ticket_id', $bundleTicketId)->where('active', true)->count(),
                    'reject' => BundleTicketReject::where('bundle_ticket_id', $bundleTicketId)->where('active', true)->count(),
                    'rework' => BundleTicketRework::where('bundle_ticket_id', $bundleTicketId)->where('active', true)->count(),
                    'rework_return' => BundleTicketReworkReturn::where('bundle_ticket_id', $bundleTicketId)->where('active', true)->count(),
                ];

                // No extra cascade needed for the rework-return-spawned
                // secondary/reject rows here (unlike the single-event delete
                // above) — BundleTicketReworkReturn always records against
                // the SAME bundle_ticket_id it resolves, so every row it
                // spawned is already covered by these four blanket updates.
                BundleTicketSecondary::where('bundle_ticket_id', $bundleTicketId)->where('active', true)
                    ->update(['active' => false, 'updated_by' => Auth::id()]);
                BundleTicketReject::where('bundle_ticket_id', $bundleTicketId)->where('active', true)
                    ->update(['active' => false, 'updated_by' => Auth::id()]);
                BundleTicketRework::where('bundle_ticket_id', $bundleTicketId)->where('active', true)
                    ->update(['active' => false, 'updated_by' => Auth::id()]);
                BundleTicketReworkReturn::where('bundle_ticket_id', $bundleTicketId)->where('active', true)
                    ->update(['active' => false, 'updated_by' => Auth::id()]);

                return $counts;
            });

            return $this->success('All scan events for this ticket deleted successfully.', [
                'bundle_ticket_id' => $bundleTicketId,
                'deleted_counts' => $deletedCounts,
            ]);
        } catch (Exception $e) {
            return $this->error('Failed to delete ticket scan events.', $e->getMessage(), 500);
        }
    }

    /**
     * Every ticket on a bundle's route, plus a precomputed "does it have
     * any active event" map — the shared inputs every eligibility check
     * below needs, resolved once per bundle instead of re-querying per
     * ticket comparison.
     *
     * @return array{0: \Illuminate\Support\Collection<int, BundleTicket>, 1: array<int, bool>}
     */
    private function loadBundleTicketActivity(int $bundleId): array
    {
        $bundleTickets = BundleTicket::with('workOrderOperation.routingOperationMaster')
            ->where('bundle_id', $bundleId)
            ->get();

        $activeMap = [];
        foreach ($bundleTickets as $bt) {
            $activeMap[$bt->id] = $this->ticketHasActiveEvent($bt->id);
        }

        return [$bundleTickets, $activeMap];
    }

    /**
     * The other ticket, if any, that currently blocks $ticket from being
     * touched — see the class docblock for exactly what "after" means
     * (greater seq anywhere, or this same operation's own OUT ticket).
     * Null means $ticket is at its bundle's frontier.
     */
    private function findBlockingTicket(BundleTicket $ticket, $bundleTickets, array $activeMap): ?BundleTicket
    {
        $seq = optional(optional($ticket->workOrderOperation)->routingOperationMaster)->seq;

        foreach ($bundleTickets as $other) {
            if ($other->id === $ticket->id || empty($activeMap[$other->id])) {
                continue;
            }

            $otherSeq = optional(optional($other->workOrderOperation)->routingOperationMaster)->seq;
            if ($seq === null || $otherSeq === null) {
                // No reliable sequence info for one side — don't block on
                // a comparison we can't actually make.
                continue;
            }

            if ($otherSeq > $seq) {
                return $other;
            }

            if (
                $otherSeq === $seq
                && $other->work_order_operation_id === $ticket->work_order_operation_id
                && $ticket->direction === 'IN'
                && $other->direction === 'OUT'
            ) {
                return $other;
            }
        }

        return null;
    }

    private function isTicketAtFrontier(BundleTicket $ticket, $bundleTickets, array $activeMap): bool
    {
        return $this->findBlockingTicket($ticket, $bundleTickets, $activeMap) === null;
    }

    private function describeBlocker(BundleTicket $blocker): string
    {
        $rom = optional(optional($blocker->workOrderOperation)->routingOperationMaster);
        $label = optional($rom->operation)->description ?? optional($rom->operation)->operation_code ?? 'a later step';

        return "This isn't at the frontier of the bundle's route yet — {$blocker->direction} on {$label} already has activity. Clear that (and anything after it) first.";
    }

    private function ticketHasActiveEvent(int $ticketId): bool
    {
        return BundleTicketSecondary::where('bundle_ticket_id', $ticketId)->where('active', true)->exists()
            || BundleTicketReject::where('bundle_ticket_id', $ticketId)->where('active', true)->exists()
            || BundleTicketRework::where('bundle_ticket_id', $ticketId)->where('active', true)->exists()
            || BundleTicketReworkReturn::where('bundle_ticket_id', $ticketId)->where('active', true)->exists();
    }

    /**
     * The most recent active event on one specific ticket, across all 4
     * event tables — created_at comparison is safe here because everything
     * being compared happened against the same operation/direction.
     */
    private function latestEventForTicket(int $ticketId): ?array
    {
        $candidates = [];

        $row = BundleTicketSecondary::where('bundle_ticket_id', $ticketId)->where('active', true)
            ->orderByDesc('created_at')->orderByDesc('id')->first();
        if ($row) {
            $candidates[] = ['type' => 'SECONDARY', 'id' => $row->id, 'created_at' => $row->created_at];
        }

        $row = BundleTicketReject::where('bundle_ticket_id', $ticketId)->where('active', true)
            ->orderByDesc('created_at')->orderByDesc('id')->first();
        if ($row) {
            $candidates[] = ['type' => 'REJECT', 'id' => $row->id, 'created_at' => $row->created_at];
        }

        $row = BundleTicketRework::where('bundle_ticket_id', $ticketId)->where('active', true)
            ->orderByDesc('created_at')->orderByDesc('id')->first();
        if ($row) {
            $candidates[] = ['type' => 'REWORK', 'id' => $row->id, 'created_at' => $row->created_at];
        }

        $row = BundleTicketReworkReturn::where('bundle_ticket_id', $ticketId)->where('active', true)
            ->orderByDesc('created_at')->orderByDesc('id')->first();
        if ($row) {
            $candidates[] = ['type' => 'REWORK_RETURN', 'id' => $row->id, 'created_at' => $row->created_at];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function ($a, $b) {
            $cmp = $b['created_at']->timestamp <=> $a['created_at']->timestamp;
            return $cmp !== 0 ? $cmp : ($b['id'] <=> $a['id']);
        });

        $winner = $candidates[0];
        $winner['created_at'] = $winner['created_at']->format('Y-m-d H:i:s');
        $winner['bundle_ticket_id'] = $ticketId;

        return $winner;
    }

    /**
     * Flatten one BundleTicketSecondary/Reject/Rework/ReworkReturn row into
     * the shape the audit tree's leaf nodes display.
     *
     * @param BundleTicketSecondary|BundleTicketReject|BundleTicketRework|BundleTicketReworkReturn $entry
     */
    private function formatEvent($entry, string $type, $userEmails, array $ticketEligibility): array
    {
        $elig = $ticketEligibility[$entry->bundle_ticket_id] ?? null;
        $latest = $elig['latest'] ?? null;
        $isLatest = $latest && $latest['type'] === $type && (int) $latest['id'] === (int) $entry->id;

        $qty = 0;
        if ($type === 'SECONDARY') {
            $qty = $entry->scan_qty;
        } elseif ($type === 'REJECT') {
            $qty = $entry->reject_qty;
        } elseif ($type === 'REWORK') {
            $qty = $entry->rework_qty;
        } elseif ($type === 'REWORK_RETURN') {
            $qty = $entry->return_qty;
        }

        $reason = null;
        if ($type === 'REJECT') {
            $reason = optional($entry->reason)->description ?: $entry->reject_reason;
        } elseif ($type === 'REWORK' || $type === 'REWORK_RETURN') {
            $reason = optional($entry->reason)->description;
        }

        return [
            'id' => $entry->id,
            'type' => $type,
            'bundle_ticket_id' => $entry->bundle_ticket_id,
            'qty' => $qty,
            'reject_qty' => $type === 'REWORK_RETURN' ? $entry->reject_qty : null,
            'reason' => $reason,
            'remarks' => ($type === 'REWORK' || $type === 'REWORK_RETURN') ? $entry->remarks : null,
            'team_name' => optional(optional($entry->dailyShiftTeam)->team)->team_name,
            'created_by' => $userEmails[$entry->created_by] ?? null,
            'created_at' => optional($entry->created_at)->format('Y-m-d H:i:s'),
            'is_latest' => $isLatest,
        ];
    }

    /**
     * Build a consistent success JSON response.
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
