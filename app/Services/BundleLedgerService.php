<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\BundleTicket;
use App\Models\BundleTicketReject;
use App\Models\BundleTicketRework;
use App\Models\BundleTicketReworkReturn;
use App\Models\BundleTicketSecondary;
use App\Models\WorkOrderOperation;

/**
 * Builds the quantity ledger for a bundle's whole route: per-ticket
 * scanned/rejected/outstanding-rework totals, the per-seq upstream
 * "release" cap, and closures to query them.
 *
 * Extracted out of WipScanController (which owns the interactive
 * scan/lookup flow) so the WIP dashboards can reuse the exact same
 * sequencing/ledger rules read-only, without re-implementing — or
 * accidentally drifting from — the logic that determines what's blocked,
 * what's resolved, and what's still outstanding on a bundle's route.
 *
 * @see \App\Http\Controllers\Api\WipScanController class docblock for the
 *      full explanation of the ledger model (effectiveQty vs entryCap,
 *      why outstanding rework doesn't shrink the ceiling, etc).
 */
class BundleLedgerService
{
    /**
     * @return array{workOrderOperations: \Illuminate\Support\Collection, tickets: \Illuminate\Support\Collection, ticketFor: \Closure, scanned: \Closure, rejected: \Closure, outstandingRework: \Closure, effectiveQty: \Closure, ticketRemaining: \Closure, ticketResolved: \Closure, resolved: \Closure, entryCapBySeq: array<int,int|null>, entrySourceBySeq: array<int,array>}
     */
    public function build(Bundle $bundle, bool $forWrite = false): array
    {
        $workOrderOperations = WorkOrderOperation::with('routingOperationMaster.operation')
            ->where('work_order_id', $bundle->work_order_id)
            ->where('active', true)
            ->get()
            ->sortBy(fn ($woo) => (int) $woo->routingOperationMaster->seq)
            ->values();

        // Pre-generated tickets (one per work_order_operation x direction) —
        // scanning never creates these, it only records quantity against them.
        $ticketsQuery = BundleTicket::with('workOrderOperation.routingOperationMaster.operation')
            ->where('bundle_id', $bundle->id)
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
        $reworkSentTotals = BundleTicketRework::whereIn('bundle_ticket_id', $ticketIds)
            ->where('active', true)
            ->groupBy('bundle_ticket_id')
            ->selectRaw('bundle_ticket_id, SUM(rework_qty) as total')
            ->pluck('total', 'bundle_ticket_id');
        $reworkReturnedTotals = BundleTicketReworkReturn::whereIn('bundle_ticket_id', $ticketIds)
            ->where('active', true)
            ->groupBy('bundle_ticket_id')
            ->selectRaw('bundle_ticket_id, SUM(return_qty + reject_qty) as total')
            ->pluck('total', 'bundle_ticket_id');

        $scanned = fn (BundleTicket $t) => (int) ($scannedTotals[$t->id] ?? 0);
        $rejected = fn (BundleTicket $t) => (int) ($rejectedTotals[$t->id] ?? 0);
        $outstandingRework = fn (BundleTicket $t) => max(0, (int) ($reworkSentTotals[$t->id] ?? 0) - (int) ($reworkReturnedTotals[$t->id] ?? 0));
        $accounted = fn (BundleTicket $t) => $scanned($t) + $rejected($t) + $outstandingRework($t);

        // A ticket's nominal qty is always the bundle's original qty — it
        // never shrinks even when an upstream reject means that much can
        // physically never arrive. Using it directly as the ceiling would
        // leave every downstream ticket permanently "short" after any
        // reject: it could never be marked resolved, which would both wedge
        // an operation's own IN→OUT fallthrough (stuck waiting for qty that
        // will never come) and block the bundle from ever completing.
        // effectiveQty() is the live, monotonically-shrinking true ceiling:
        // each ticket's ceiling is the previous ticket's ceiling minus
        // whatever has actually been rejected there so far (outstanding
        // rework is NOT subtracted — it may still resolve as good).
        $effectiveQtyByTicketId = [];
        $seqGroups = $workOrderOperations->groupBy(fn ($woo) => (int) $woo->routingOperationMaster->seq);
        $prevSeqCeiling = null; // null = uncapped (first seq — bounded only by the ticket's own qty)
        foreach ($seqGroups as $seq => $woosAtSeq) {
            $exitCeilings = [];
            foreach ($woosAtSeq as $woo) {
                $rom = $woo->routingOperationMaster;
                $chain = $prevSeqCeiling;
                if ($rom->in && ($inTicket = $ticketFor($woo->id, 'IN'))) {
                    $ceil = $chain === null ? $inTicket->qty : min($inTicket->qty, $chain);
                    $effectiveQtyByTicketId[$inTicket->id] = $ceil;
                    $chain = $ceil - $rejected($inTicket);
                }
                if ($rom->out && ($outTicket = $ticketFor($woo->id, 'OUT'))) {
                    $ceil = $chain === null ? $outTicket->qty : min($outTicket->qty, $chain);
                    $effectiveQtyByTicketId[$outTicket->id] = $ceil;
                    $chain = $ceil - $rejected($outTicket);
                }
                $exitCeilings[] = $chain;
            }
            $exitCeilings = array_filter($exitCeilings, fn ($c) => $c !== null);
            $prevSeqCeiling = empty($exitCeilings) ? null : min($exitCeilings);
        }
        $effectiveQty = fn (BundleTicket $t) => $effectiveQtyByTicketId[$t->id] ?? $t->qty;

        $ticketRemaining = fn (BundleTicket $t) => max(0, $effectiveQty($t) - $accounted($t));
        $ticketResolved = fn (BundleTicket $t) => $accounted($t) >= $effectiveQty($t) && $outstandingRework($t) === 0;
        $resolved = function ($woo) use ($ticketFor, $accounted, $effectiveQty, $outstandingRework) {
            $rom = $woo->routingOperationMaster;
            if ($rom->in) {
                $ticket = $ticketFor($woo->id, 'IN');
                if (!$ticket || $accounted($ticket) < $effectiveQty($ticket) || $outstandingRework($ticket) > 0) {
                    return false;
                }
            }
            if ($rom->out) {
                $ticket = $ticketFor($woo->id, 'OUT');
                if (!$ticket || $accounted($ticket) < $effectiveQty($ticket) || $outstandingRework($ticket) > 0) {
                    return false;
                }
            }
            return true;
        };

        // entryCapBySeq[seq] = qty actually released so far by the previous
        // present seq level (MIN across parallel operations at that level)
        // — how much is available to draw from RIGHT NOW, as opposed to
        // effectiveQty()'s theoretical final ceiling. null for the first
        // seq in the route — bounded only by each ticket's own qty.
        //
        // entrySourceBySeq[seq] = one entry per operation at the PREVIOUS
        // seq level (there can be more than one when they run in parallel),
        // so callers can name exactly which upstream operation/direction a
        // qty came from — or which one is the bottleneck when blocked.
        $entryCapBySeq = [];
        $entrySourceBySeq = [];
        $releaseInfoOf = function ($woo) use ($ticketFor, $scanned) {
            $rom = $woo->routingOperationMaster;
            $direction = $rom->out ? 'OUT' : ($rom->in ? 'IN' : null);
            $ticket = $direction ? $ticketFor($woo->id, $direction) : null;

            return [
                'operation_code' => optional($rom->operation)->operation_code,
                'operation_description' => optional($rom->operation)->description,
                'direction' => $direction,
                'released' => $ticket ? $scanned($ticket) : 0,
            ];
        };
        $prevRelease = null;
        $prevSources = [];
        foreach ($seqGroups as $seq => $woosAtSeq) {
            $entryCapBySeq[$seq] = $prevRelease;
            $entrySourceBySeq[$seq] = $prevSources;
            $releaseInfos = $woosAtSeq->map($releaseInfoOf)->values();
            $prevRelease = $releaseInfos->min('released');
            $prevSources = $releaseInfos->all();
        }

        // How much of a ticket's remaining qty can actually be worked RIGHT
        // NOW, as opposed to ticketRemaining()'s "still owed eventually" —
        // the same cap-minus-consumed math resolveScanTarget() applies to
        // the one operation an operator is standing at, generalized here to
        // every ticket on the route so a dashboard can tell "queued and
        // ready" apart from "blocked, waiting on an upstream release".
        $availableNow = function (BundleTicket $t) use ($ticketFor, $scanned, $rejected, $outstandingRework, $ticketRemaining, $entryCapBySeq) {
            $remaining = $ticketRemaining($t);
            if ($remaining <= 0) {
                return 0;
            }
            $woo = $t->workOrderOperation;
            $rom = $woo->routingOperationMaster;
            $seq = (int) $rom->seq;
            $entryCap = $entryCapBySeq[$seq] ?? null;

            $cap = $t->direction === 'IN'
                ? $entryCap
                : ($rom->in ? $scanned($ticketFor($woo->id, 'IN')) : $entryCap);

            if ($cap === null) {
                return $remaining;
            }

            $consumed = $scanned($t) + $rejected($t) + $outstandingRework($t);
            return max(0, min($remaining, $cap - $consumed));
        };

        return [
            'workOrderOperations' => $workOrderOperations,
            'tickets' => $tickets,
            'ticketFor' => $ticketFor,
            'scanned' => $scanned,
            'rejected' => $rejected,
            'outstandingRework' => $outstandingRework,
            'effectiveQty' => $effectiveQty,
            'ticketRemaining' => $ticketRemaining,
            'ticketResolved' => $ticketResolved,
            'resolved' => $resolved,
            'entryCapBySeq' => $entryCapBySeq,
            'entrySourceBySeq' => $entrySourceBySeq,
            'availableNow' => $availableNow,
        ];
    }
}
