<?php

namespace App\Http\Controllers\Api;

use App\DailyShiftTeam;
use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\BundleTicketReject;
use App\Models\BundleTicketRework;
use App\Models\BundleTicketReworkReturn;
use App\Models\BundleTicketSecondary;
use App\Services\BundleLedgerService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only reporting on top of the Production WIP Scanning ledger
 * (App\Services\BundleLedgerService) — two audiences, two endpoints:
 *
 * floor()      — a shop-floor screen: what's happening right now (per
 *                station queued/blocked qty), what needs attention
 *                (bundles stuck waiting on an upstream release, or with a
 *                rework backlog aging), and today's per-team scoreboard.
 * management() — a top-level view: daily throughput/reject/rework trend
 *                over a selectable window, with a headline summary.
 *
 * Both are pure aggregation — nothing here writes to the ledger.
 */
class WipDashboardController extends Controller
{
    private BundleLedgerService $ledger;

    public function __construct(BundleLedgerService $ledger)
    {
        $this->ledger = $ledger;
    }

    /**
     * Production-floor snapshot: live per-station queue/blocked state,
     * problem alerts, today's team scoreboard, and a live main-model/model/
     * batch breakdown of what's actually sitting on the floor right now —
     * all in one call so the screen can be built as a single page.
     */
    public function floor(): JsonResponse
    {
        try {
            $today = now()->startOfDay();

            $stations = [];
            $alerts = [];
            $batches = [];

            // Only bundles with something still outstanding on their route
            // are interesting here — a fully resolved bundle has nothing to
            // show on a "what's happening right now" screen.
            Bundle::where('active', true)
                ->with(['workOrder.batchDetail.batch', 'workOrder.batchDetail.model.mainModel'])
                ->orderBy('id')
                ->chunk(50, function ($bundles) use (&$stations, &$alerts, &$batches) {
                    foreach ($bundles as $bundle) {
                        $this->accumulateFloorData($bundle, $stations, $alerts, $batches);
                    }
                });

            $stationList = collect($stations)->map(function ($station) {
                $station['bundles_in_progress'] = count($station['bundles_in_progress']);
                return $station;
            })->values()->sortBy('operation_code')->values();

            // Oldest/most urgent first.
            $alertList = collect($alerts)->sortByDesc('age_minutes')->values()->take(50);

            $batchList = collect($batches)->map(function ($batch) {
                $batch['bundles_in_progress'] = count($batch['bundles_in_progress']);
                return $batch;
            })->values()->sortByDesc(function ($batch) {
                return $batch['queued_qty'] + $batch['blocked_qty'] + $batch['outstanding_rework'];
            })->values();

            $teamScoreboard = $this->teamScoreboard($today);

            return $this->success('Floor dashboard retrieved successfully.', [
                'generated_at' => now()->toIso8601String(),
                'stations' => $stationList,
                'alerts' => $alertList,
                'batch_breakdown' => $batchList,
                'team_scoreboard' => $teamScoreboard,
            ]);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve floor dashboard.', $e->getMessage(), 500);
        }
    }

    /**
     * Fold one bundle's ledger into the running $stations/$alerts/$batches
     * totals. Kept separate from floor() so the per-bundle work (the only
     * part that scales with data volume) is easy to read on its own.
     */
    private function accumulateFloorData(Bundle $bundle, array &$stations, array &$alerts, array &$batches): void
    {
        $ledger = $this->ledger->build($bundle);
        $workOrderOperations = $ledger['workOrderOperations'];
        if ($workOrderOperations->every($ledger['resolved'])) {
            return;
        }

        $tickets = $ledger['tickets'];
        $ticketIds = $tickets->pluck('id');
        if ($ticketIds->isEmpty()) {
            return;
        }

        // This bundle's place in the main-model / model / batch hierarchy —
        // resolved once per bundle (it doesn't vary per ticket) via
        // bundle -> work_order -> batch_detail -> batch/model -> main_model.
        // A batch can be split across models (batch_details), so the
        // breakdown key is the (batch, model) pair, not the batch alone.
        $batchDetail = optional($bundle->workOrder)->batchDetail;
        $modelRecord = optional($batchDetail)->model;
        $mainModelRecord = optional($modelRecord)->mainModel;
        $batchKey = $batchDetail ? ($batchDetail->batch_id . '-' . $batchDetail->model_id) : 'unassigned';

        if (!isset($batches[$batchKey])) {
            $batches[$batchKey] = [
                'batch_id' => optional($batchDetail)->batch_id,
                'batch_no' => optional(optional($batchDetail)->batch)->batch_no,
                'model_id' => optional($modelRecord)->id,
                'model_name' => optional($modelRecord)->name,
                'main_model_id' => optional($mainModelRecord)->id,
                'main_model_name' => optional($mainModelRecord)->name,
                'queued_qty' => 0,
                'blocked_qty' => 0,
                'outstanding_rework' => 0,
                'bundles_in_progress' => [],
            ];
        }

        // Two small extra lookups (not part of the core ledger) purely to
        // give alerts a "since" timestamp: the last time a blocked ticket's
        // upstream source actually moved, and the earliest still-open
        // rework send on a ticket with a backlog.
        $lastScanAt = BundleTicketSecondary::whereIn('bundle_ticket_id', $ticketIds)
            ->where('active', true)
            ->groupBy('bundle_ticket_id')
            ->selectRaw('bundle_ticket_id, MAX(created_at) as last_at')
            ->pluck('last_at', 'bundle_ticket_id');
        $oldestOpenReworkAt = BundleTicketRework::whereIn('bundle_ticket_id', $ticketIds)
            ->where('active', true)
            ->groupBy('bundle_ticket_id')
            ->selectRaw('bundle_ticket_id, MIN(created_at) as first_at')
            ->pluck('first_at', 'bundle_ticket_id');

        $ticketRemaining = $ledger['ticketRemaining'];
        $availableNow = $ledger['availableNow'];
        $outstandingRework = $ledger['outstandingRework'];
        $entrySourceBySeq = $ledger['entrySourceBySeq'];
        $ticketFor = $ledger['ticketFor'];
        $scanned = $ledger['scanned'];

        foreach ($tickets as $ticket) {
            $woo = $ticket->workOrderOperation;
            $rom = optional($woo)->routingOperationMaster;
            if (!$rom) {
                continue;
            }
            $opId = $rom->operation_id;

            if (!isset($stations[$opId])) {
                $stations[$opId] = [
                    'operation_id' => $opId,
                    'operation_code' => optional($rom->operation)->operation_code,
                    'operation_description' => optional($rom->operation)->description,
                    'queued_qty' => 0,
                    'blocked_qty' => 0,
                    'outstanding_rework' => 0,
                    'bundles_in_progress' => [],
                ];
            }

            $remaining = $ticketRemaining($ticket);
            $available = $availableNow($ticket);
            $rework = $outstandingRework($ticket);

            $stations[$opId]['queued_qty'] += $available;
            $stations[$opId]['blocked_qty'] += max(0, $remaining - $available);
            $stations[$opId]['outstanding_rework'] += $rework;
            $stations[$opId]['bundles_in_progress'][$bundle->id] = true;

            $batches[$batchKey]['queued_qty'] += $available;
            $batches[$batchKey]['blocked_qty'] += max(0, $remaining - $available);
            $batches[$batchKey]['outstanding_rework'] += $rework;
            $batches[$batchKey]['bundles_in_progress'][$bundle->id] = true;

            if ($remaining > 0 && $available <= 0) {
                $seq = (int) $rom->seq;
                // A blocked OUT ticket on an operation that also has its own
                // IN step is gated by THAT step, not by whatever sits at the
                // previous seq — mirrors resolveScanTarget()'s
                // previousStepFor() same-operation special case exactly, so
                // the alert names the right bottleneck (e.g. "WD OUT is
                // blocked by WD's own IN", not by the bundle's first step).
                if ($ticket->direction === 'OUT' && $rom->in) {
                    $inTicket = $ticketFor($woo->id, 'IN');
                    $source = [
                        'operation_code' => optional($rom->operation)->operation_code,
                        'operation_description' => optional($rom->operation)->description,
                        'direction' => 'IN',
                        'released' => $inTicket ? $scanned($inTicket) : 0,
                        'same_operation' => true,
                    ];
                } else {
                    $source = collect($entrySourceBySeq[$seq] ?? [])->sortBy('released')->first();
                }
                // MAX(created_at) from the grouped query comes back as a raw
                // string, not a Carbon instance — normalize both possible
                // sources (that string, or the bundle's own Carbon
                // created_at) through Carbon::parse() before formatting.
                $since = $lastScanAt[$ticket->id] ?? $bundle->created_at;
                $since = $since ? \Carbon\Carbon::parse($since) : null;
                $alerts[] = [
                    'type' => 'WAITING_ON_UPSTREAM',
                    'bundle_id' => $bundle->id,
                    'bundle_ticket_id' => $ticket->id,
                    'operation_code' => optional($rom->operation)->operation_code,
                    'operation_description' => optional($rom->operation)->description,
                    'direction' => $ticket->direction,
                    'stuck_qty' => $remaining,
                    'source' => $source,
                    'since' => optional($since)->toIso8601String(),
                    'age_minutes' => $since ? now()->diffInMinutes($since) : 0,
                ];
            }

            if ($rework > 0) {
                $since = $oldestOpenReworkAt[$ticket->id] ?? null;
                $since = $since ? \Carbon\Carbon::parse($since) : null;
                $alerts[] = [
                    'type' => 'REWORK_BACKLOG',
                    'bundle_id' => $bundle->id,
                    'bundle_ticket_id' => $ticket->id,
                    'operation_code' => optional($rom->operation)->operation_code,
                    'operation_description' => optional($rom->operation)->description,
                    'direction' => $ticket->direction,
                    'outstanding_qty' => $rework,
                    'since' => optional($since)->toIso8601String(),
                    'age_minutes' => $since ? now()->diffInMinutes($since) : 0,
                ];
            }
        }
    }

    /**
     * Today's per-team scan/reject/rework totals and rates — a running
     * scoreboard for the floor screen.
     */
    private function teamScoreboard($today)
    {
        $scanned = BundleTicketSecondary::where('active', true)
            ->where('created_at', '>=', $today)
            ->groupBy('daily_shift_team_id')
            ->selectRaw('daily_shift_team_id, SUM(scan_qty) as qty')
            ->pluck('qty', 'daily_shift_team_id');
        $rejected = BundleTicketReject::where('active', true)
            ->where('created_at', '>=', $today)
            ->groupBy('daily_shift_team_id')
            ->selectRaw('daily_shift_team_id, SUM(reject_qty) as qty')
            ->pluck('qty', 'daily_shift_team_id');
        $reworked = BundleTicketRework::where('active', true)
            ->where('created_at', '>=', $today)
            ->groupBy('daily_shift_team_id')
            ->selectRaw('daily_shift_team_id, SUM(rework_qty) as qty')
            ->pluck('qty', 'daily_shift_team_id');
        // Rework sends this team is still waiting on: total sent minus
        // whatever the rework station has already resolved (returned good
        // or rejected after rework) — the pending slice of the team's own
        // rework_qty above.
        $reworkResolved = BundleTicketReworkReturn::where('active', true)
            ->where('created_at', '>=', $today)
            ->groupBy('daily_shift_team_id')
            ->selectRaw('daily_shift_team_id, SUM(return_qty + reject_qty) as qty')
            ->pluck('qty', 'daily_shift_team_id');

        $teamIds = $scanned->keys()->concat($rejected->keys())->concat($reworked->keys())->unique();
        $teams = DailyShiftTeam::with('team')->whereIn('id', $teamIds)->get()->keyBy('id');

        return $teamIds->map(function ($id) use ($scanned, $rejected, $reworked, $reworkResolved, $teams) {
            $scannedQty = (int) ($scanned[$id] ?? 0);
            $rejectedQty = (int) ($rejected[$id] ?? 0);
            $reworkQty = (int) ($reworked[$id] ?? 0);
            $reworkOutstanding = max(0, $reworkQty - (int) ($reworkResolved[$id] ?? 0));
            $handled = $scannedQty + $rejectedQty + $reworkQty;
            $team = $teams[$id] ?? null;

            // Team WIP, in digits: what this team has scanned today, still
            // in play — rejects are gone for good and rework still pending
            // return hasn't rejoined good output yet, so both come off.
            $wipQty = max(0, $scannedQty - $rejectedQty - $reworkOutstanding);

            return [
                'daily_shift_team_id' => $id,
                'team_name' => optional(optional($team)->team)->team_name,
                'scanned_qty' => $scannedQty,
                'rejected_qty' => $rejectedQty,
                'rework_qty' => $reworkQty,
                'wip_qty' => $wipQty,
                'reject_rate_pct' => $handled > 0 ? round($rejectedQty / $handled * 100, 1) : 0,
                'rework_rate_pct' => $handled > 0 ? round($reworkQty / $handled * 100, 1) : 0,
            ];
        })->sortByDesc('scanned_qty')->values();
    }

    /**
     * Management trend view: daily scanned/rejected/rework totals and
     * rates over the requested window, plus a headline summary — the
     * "is quality/output improving or slipping" question.
     */
    public function management(Request $request): JsonResponse
    {
        try {
            $days = (int) $request->input('days', 30);
            $days = max(1, min($days, 365));
            $from = now()->startOfDay()->subDays($days - 1);

            $scannedByDay = BundleTicketSecondary::where('active', true)
                ->where('created_at', '>=', $from)
                ->selectRaw('DATE(created_at) as day, SUM(scan_qty) as qty')
                ->groupBy('day')
                ->pluck('qty', 'day');
            $rejectedByDay = BundleTicketReject::where('active', true)
                ->where('created_at', '>=', $from)
                ->selectRaw('DATE(created_at) as day, SUM(reject_qty) as qty')
                ->groupBy('day')
                ->pluck('qty', 'day');
            $reworkByDay = BundleTicketRework::where('active', true)
                ->where('created_at', '>=', $from)
                ->selectRaw('DATE(created_at) as day, SUM(rework_qty) as qty')
                ->groupBy('day')
                ->pluck('qty', 'day');

            $daily = [];
            for ($i = 0; $i < $days; $i++) {
                $date = $from->copy()->addDays($i)->toDateString();
                $scannedQty = (int) ($scannedByDay[$date] ?? 0);
                $rejectedQty = (int) ($rejectedByDay[$date] ?? 0);
                $reworkQty = (int) ($reworkByDay[$date] ?? 0);
                $handled = $scannedQty + $rejectedQty + $reworkQty;

                $daily[] = [
                    'date' => $date,
                    'scanned_qty' => $scannedQty,
                    'rejected_qty' => $rejectedQty,
                    'rework_qty' => $reworkQty,
                    'reject_rate_pct' => $handled > 0 ? round($rejectedQty / $handled * 100, 1) : 0,
                    'rework_rate_pct' => $handled > 0 ? round($reworkQty / $handled * 100, 1) : 0,
                ];
            }

            $totalScanned = array_sum(array_column($daily, 'scanned_qty'));
            $totalRejected = array_sum(array_column($daily, 'rejected_qty'));
            $totalRework = array_sum(array_column($daily, 'rework_qty'));
            $totalHandled = $totalScanned + $totalRejected + $totalRework;

            return $this->success('Management dashboard retrieved successfully.', [
                'range_days' => $days,
                'summary' => [
                    'total_scanned' => $totalScanned,
                    'total_rejected' => $totalRejected,
                    'total_rework_sent' => $totalRework,
                    'overall_reject_rate_pct' => $totalHandled > 0 ? round($totalRejected / $totalHandled * 100, 1) : 0,
                    'overall_rework_rate_pct' => $totalHandled > 0 ? round($totalRework / $totalHandled * 100, 1) : 0,
                ],
                'daily' => $daily,
                'batch_breakdown' => $this->batchBreakdown($from),
            ]);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve management dashboard.', $e->getMessage(), 500);
        }
    }

    /**
     * Production output for the selected window, rolled up by main model /
     * model / batch — the "which product lines are we actually running,
     * and how clean is each one" view a production manager or MD needs,
     * on top of the day-by-day trend above.
     */
    private function batchBreakdown($from)
    {
        $scanned = $this->batchQtyByGroup('bundle_ticket_secondaries', 'scan_qty', $from);
        $rejected = $this->batchQtyByGroup('bundle_ticket_rejects', 'reject_qty', $from);
        $reworked = $this->batchQtyByGroup('bundle_ticket_reworks', 'rework_qty', $from);

        $keys = $scanned->keys()->concat($rejected->keys())->concat($reworked->keys())->unique();

        return $keys->map(function ($key) use ($scanned, $rejected, $reworked) {
            $row = $scanned[$key] ?? $rejected[$key] ?? $reworked[$key];
            $scannedQty = (int) optional($scanned[$key] ?? null)->qty;
            $rejectedQty = (int) optional($rejected[$key] ?? null)->qty;
            $reworkQty = (int) optional($reworked[$key] ?? null)->qty;
            $handled = $scannedQty + $rejectedQty + $reworkQty;

            return [
                'main_model_id' => $row->main_model_id,
                'main_model_name' => $row->main_model_name,
                'model_id' => $row->model_id,
                'model_name' => $row->model_name,
                'batch_id' => $row->batch_id,
                'batch_no' => $row->batch_no,
                'scanned_qty' => $scannedQty,
                'rejected_qty' => $rejectedQty,
                'rework_qty' => $reworkQty,
                'reject_rate_pct' => $handled > 0 ? round($rejectedQty / $handled * 100, 1) : 0,
                'rework_rate_pct' => $handled > 0 ? round($reworkQty / $handled * 100, 1) : 0,
            ];
        })->sortByDesc('scanned_qty')->values();
    }

    /**
     * SUM($qtyColumn) from a scan/reject/rework ledger table, grouped by
     * (batch, model) via bundle -> work_order -> batch_detail — the
     * authoritative model for a work order, since one batch can be split
     * across several models (batch_details).
     */
    private function batchQtyByGroup(string $table, string $qtyColumn, $from)
    {
        return DB::table("$table as t")
            ->join('bundle_tickets as bt', 'bt.id', '=', 't.bundle_ticket_id')
            ->join('bundles as b', 'b.id', '=', 'bt.bundle_id')
            ->join('work_orders as wo', 'wo.id', '=', 'b.work_order_id')
            ->join('batch_details as bd', 'bd.id', '=', 'wo.batch_detail_id')
            ->join('batches as ba', 'ba.id', '=', 'bd.batch_id')
            ->join('models as mo', 'mo.id', '=', 'bd.model_id')
            ->leftJoin('main_models as mm', 'mm.id', '=', 'mo.main_model_id')
            ->where('t.active', true)
            ->where('t.created_at', '>=', $from)
            ->groupBy('ba.id', 'ba.batch_no', 'mo.id', 'mo.name', 'mm.id', 'mm.name')
            ->selectRaw("
                ba.id as batch_id, ba.batch_no,
                mo.id as model_id, mo.name as model_name,
                mm.id as main_model_id, mm.name as main_model_name,
                SUM(t.$qtyColumn) as qty
            ")
            ->get()
            ->keyBy(function ($row) {
                return $row->batch_id . '-' . $row->model_id;
            });
    }

    private function success(string $message, $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    private function error(string $message, $errors = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
