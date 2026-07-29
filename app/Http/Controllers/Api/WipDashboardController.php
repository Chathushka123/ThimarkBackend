<?php

namespace App\Http\Controllers\Api;

use App\DailyShift;
use App\DailyShiftTeam;
use App\Team;
use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\BundleTicketReject;
use App\Models\BundleTicketRework;
use App\Models\BundleTicketReworkReturn;
use App\Models\BundleTicketSecondary;
use App\Services\BundleLedgerService;
use Carbon\Carbon;
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
     * The current shift(s) actually running right now
     * (start_date_time <= now <= end_date_time) plus the ids of every
     * DailyShiftTeam under them, and the hour window (min start, max end)
     * those shifts cover — the floor screen's whole "current shift" scope.
     *
     * A scan's daily_shift_team_id is set explicitly at scan time (see
     * WipScanController::scan()) to whichever team the operator picked from
     * their currently-active teams (WipScanController::myTeams()), so
     * filtering by this id set is the correct way to select "this shift's
     * activity" — a calendar-day cutoff breaks for any shift that runs past
     * midnight, and would also wrongly include a second, unrelated shift
     * that happens to fall on the same calendar day.
     */
    private function currentShiftScope(): array
    {
        $now = now();
        $shifts = DailyShift::where('active', true)
            ->where('start_date_time', '<=', $now)
            ->where('end_date_time', '>=', $now)
            ->get();

        $teamIds = DailyShiftTeam::where('active', true)
            ->whereIn('daily_shift_id', $shifts->pluck('id'))
            ->pluck('id');

        $rangeStart = $shifts->isEmpty() ? $now->copy()->startOfDay() : $shifts->min('start_date_time');
        $rangeEnd = $shifts->isEmpty() ? $now->copy()->endOfDay() : $shifts->max('end_date_time');

        if (!$rangeStart instanceof Carbon) {
            $rangeStart = Carbon::parse($rangeStart);
        }
        if (!$rangeEnd instanceof Carbon) {
            $rangeEnd = Carbon::parse($rangeEnd);
        }
        if ($rangeEnd->lte($rangeStart)) {
            $rangeEnd = $rangeStart->copy()->addHour();
        }

        return [$teamIds, $rangeStart, $rangeEnd];
    }

    /**
     * Same idea as currentShiftScope(), for a date range: every
     * DailyShiftTeam under a DailyShift whose window overlaps [from, to] —
     * the management screen's "shifts in this range" scope.
     */
    private function shiftTeamIdsForRange(Carbon $from, Carbon $to)
    {
        $shiftIds = DailyShift::where('active', true)
            ->where('start_date_time', '<=', $to)
            ->where('end_date_time', '>=', $from)
            ->pluck('id');

        return DailyShiftTeam::where('active', true)
            ->whereIn('daily_shift_id', $shiftIds)
            ->pluck('id');
    }

    /**
     * Production-floor snapshot: live per-station queue/blocked state,
     * problem alerts, the current shift's team scoreboard, and a live
     * main-model/model/batch breakdown of what's actually sitting on the
     * floor right now — all in one call so the screen can be built as a
     * single page.
     */
    public function floor(): JsonResponse
    {
        try {
            [$shiftTeamIds, $shiftStart, $shiftEnd] = $this->currentShiftScope();

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

            $teamScoreboard = $this->teamScoreboard($shiftTeamIds);

            return $this->success('Floor dashboard retrieved successfully.', [
                'generated_at' => now()->toIso8601String(),
                'stations' => $stationList,
                'alerts' => $alertList,
                'batch_breakdown' => $batchList,
                'team_scoreboard' => $teamScoreboard,
                'station_team_breakdown' => $this->stationTeamBreakdown($shiftTeamIds),
                'hourly_trend' => $this->hourlyTrend($shiftTeamIds, $shiftStart, $shiftEnd),
                'team_hourly_trend' => $this->teamHourlyTrend($shiftTeamIds, $shiftStart, $shiftEnd),
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
     * This shift's per-team scan/reject/rework totals, rates, and
     * efficiency — a running scoreboard for the floor screen (rendered as
     * cards). $shiftTeamIds is the current-shift DailyShiftTeam id set from
     * currentShiftScope() — every query below is scoped to it directly
     * rather than to a calendar-day cutoff.
     */
    private function teamScoreboard($shiftTeamIds)
    {
        // Split by bundle_tickets.direction — a team's own operation
        // routinely has both an IN ticket (pieces arriving) and an OUT
        // ticket (pieces completed/released), so summing scan_qty across
        // both without this split silently adds them together instead of
        // netting them off.
        $scannedByDirection = BundleTicketSecondary::where('bundle_ticket_secondaries.active', true)
            ->whereIn('bundle_ticket_secondaries.daily_shift_team_id', $shiftTeamIds)
            ->join('bundle_tickets as bt', 'bt.id', '=', 'bundle_ticket_secondaries.bundle_ticket_id')
            ->groupBy('daily_shift_team_id', 'bt.direction')
            ->selectRaw('daily_shift_team_id, bt.direction, SUM(scan_qty) as qty')
            ->get();
        $scannedIn = $scannedByDirection->where('direction', 'IN')->pluck('qty', 'daily_shift_team_id');
        $scannedOut = $scannedByDirection->where('direction', 'OUT')->pluck('qty', 'daily_shift_team_id');
        $rejected = BundleTicketReject::where('active', true)
            ->whereIn('daily_shift_team_id', $shiftTeamIds)
            ->groupBy('daily_shift_team_id')
            ->selectRaw('daily_shift_team_id, SUM(reject_qty) as qty')
            ->pluck('qty', 'daily_shift_team_id');
        $reworked = BundleTicketRework::where('active', true)
            ->whereIn('daily_shift_team_id', $shiftTeamIds)
            ->groupBy('daily_shift_team_id')
            ->selectRaw('daily_shift_team_id, SUM(rework_qty) as qty')
            ->pluck('qty', 'daily_shift_team_id');
        // Rework sends this team is still waiting on: total sent minus
        // whatever the rework station has already resolved (returned good
        // or rejected after rework) — the pending slice of the team's own
        // rework_qty above.
        $reworkResolved = BundleTicketReworkReturn::where('active', true)
            ->whereIn('daily_shift_team_id', $shiftTeamIds)
            ->groupBy('daily_shift_team_id')
            ->selectRaw('daily_shift_team_id, SUM(return_qty + reject_qty) as qty')
            ->pluck('qty', 'daily_shift_team_id');
        // Standard minutes earned this shift: SUM(scan_qty * smv) per team,
        // via each scan's own work-order operation — a team can float across
        // more than one operation code in a shift, so this is summed per
        // scan, not assumed uniform from the team's default operation.
        $earnedMinutes = DB::table('bundle_ticket_secondaries as bts')
            ->join('bundle_tickets as bt', 'bt.id', '=', 'bts.bundle_ticket_id')
            ->join('work_order_operations as woo', 'woo.id', '=', 'bt.work_order_operation_id')
            ->where('bts.active', true)
            ->whereIn('bts.daily_shift_team_id', $shiftTeamIds)
            ->groupBy('bts.daily_shift_team_id')
            ->selectRaw('bts.daily_shift_team_id, SUM(bts.scan_qty * woo.smv) as minutes')
            ->pluck('minutes', 'daily_shift_team_id');

        $teamIds = $scannedIn->keys()->concat($scannedOut->keys())->concat($rejected->keys())->concat($reworked->keys())->unique();
        $teams = DailyShiftTeam::with(['team.operation', 'dailyShift'])->whereIn('id', $teamIds)->get()->keyBy('id');

        return $teamIds->map(function ($id) use ($scannedIn, $scannedOut, $rejected, $reworked, $reworkResolved, $earnedMinutes, $teams) {
            $scannedInQty = (int) ($scannedIn[$id] ?? 0);
            $scannedOutQty = (int) ($scannedOut[$id] ?? 0);
            $scannedQty = $scannedInQty + $scannedOutQty;
            $rejectedQty = (int) ($rejected[$id] ?? 0);
            $reworkQty = (int) ($reworked[$id] ?? 0);
            $reworkOutstanding = max(0, $reworkQty - (int) ($reworkResolved[$id] ?? 0));
            $handled = $scannedQty + $rejectedQty + $reworkQty;
            $shiftTeam = $teams[$id] ?? null;
            $team = optional($shiftTeam)->team;

            // Team WIP, in digits: pieces this team has received (scanned
            // IN) minus what it has already released (scanned OUT), minus
            // rejects (gone for good) and rework still pending return
            // (hasn't rejoined good output yet) — what's still physically
            // in play with this team right now.
            $wipQty = max(0, $scannedInQty - $scannedOutQty - $rejectedQty - $reworkOutstanding);

            return [
                'daily_shift_team_id' => $id,
                'team_name' => optional($team)->team_name,
                'operation_id' => optional($team)->operation_id,
                'operation_code' => optional(optional($team)->operation)->operation_code,
                'operation_description' => optional(optional($team)->operation)->description,
                'scanned_qty' => $scannedQty,
                'rejected_qty' => $rejectedQty,
                'rework_qty' => $reworkQty,
                'wip_qty' => $wipQty,
                'reject_rate_pct' => $handled > 0 ? round($rejectedQty / $handled * 100, 1) : 0,
                'rework_rate_pct' => $handled > 0 ? round($reworkQty / $handled * 100, 1) : 0,
                'efficiency_pct' => $this->teamEfficiencyPct($shiftTeam, (float) ($earnedMinutes[$id] ?? 0)),
            ];
        })->sortByDesc('scanned_qty')->values();
    }

    /**
     * Efficiency = standard minutes earned / available minutes, where
     * available minutes = (now, or shift end if already past) minus shift
     * start, times the number of operators on the team — the SMV-based
     * "line efficiency" figure a production floor tracks per team.
     */
    private function teamEfficiencyPct(?DailyShiftTeam $shiftTeam, float $earnedMinutes): float
    {
        if (!$shiftTeam) {
            return 0;
        }

        $start = $shiftTeam->start_date_time ?: optional($shiftTeam->dailyShift)->start_date_time;
        if (!$start) {
            return 0;
        }
        $shiftEnd = $shiftTeam->end_date_time ?: optional($shiftTeam->dailyShift)->end_date_time;
        $end = ($shiftEnd && now()->gt($shiftEnd)) ? $shiftEnd : now();

        $elapsedMinutes = $end->gt($start) ? $start->diffInMinutes($end) : 0;
        $availableMinutes = $elapsedMinutes * (int) ($shiftTeam->no_of_operators ?? 0);

        return $availableMinutes > 0 ? round($earnedMinutes / $availableMinutes * 100, 1) : 0;
    }

    /**
     * This shift's good/reject/rework counts per (operation, team) pair —
     * feeds the floor screen's bar chart, which needs both dimensions on
     * the axis since a team isn't tied to a single operation across a
     * whole shift.
     */
    private function stationTeamBreakdown($shiftTeamIds)
    {
        $rows = function (string $table, string $qtyColumn) use ($shiftTeamIds) {
            return DB::table("$table as t")
                ->join('bundle_tickets as bt', 'bt.id', '=', 't.bundle_ticket_id')
                ->join('work_order_operations as woo', 'woo.id', '=', 'bt.work_order_operation_id')
                ->join('routing_operation_masters as rom', 'rom.id', '=', 'woo.routing_operation_master_id')
                ->join('operation_masters as om', 'om.id', '=', 'rom.operation_id')
                ->join('daily_shift_teams as dst', 'dst.id', '=', 't.daily_shift_team_id')
                ->join('teams as tm', 'tm.id', '=', 'dst.team_id')
                ->where('t.active', true)
                ->whereIn('t.daily_shift_team_id', $shiftTeamIds)
                ->groupBy('om.id', 'om.operation_code', 'om.description', 'dst.id', 'tm.team_name')
                ->selectRaw("
                    om.id as operation_id, om.operation_code, om.description as operation_description,
                    dst.id as daily_shift_team_id, tm.team_name,
                    SUM(t.$qtyColumn) as qty
                ")
                ->get()
                ->keyBy(fn ($row) => $row->operation_id . '-' . $row->daily_shift_team_id);
        };

        $good = $this->goodQtyByOperationTeam($shiftTeamIds);
        $reject = $rows('bundle_ticket_rejects', 'reject_qty');
        $rework = $rows('bundle_ticket_reworks', 'rework_qty');

        $keys = $good->keys()->concat($reject->keys())->concat($rework->keys())->unique();

        return $keys->map(function ($key) use ($good, $reject, $rework) {
            $row = $good[$key] ?? $reject[$key] ?? $rework[$key];

            return [
                'operation_id' => $row->operation_id,
                'operation_code' => $row->operation_code,
                'operation_description' => $row->operation_description,
                'daily_shift_team_id' => $row->daily_shift_team_id,
                'team_name' => $row->team_name,
                'good_qty' => (int) optional($good[$key] ?? null)->qty,
                'reject_qty' => (int) optional($reject[$key] ?? null)->qty,
                'rework_qty' => (int) optional($rework[$key] ?? null)->qty,
            ];
        })->sortBy(['operation_code', 'team_name'])->values();
    }

    /**
     * "Good" scanned qty per (operation, daily_shift_team) for this shift —
     * the floor bar chart's Good series. An operation's own OUT ticket and
     * IN ticket are scanned by the same team (see teamScoreboard()'s
     * docblock), so summing scan_qty across both directions double-counts
     * every piece that has already been scanned in and then out again.
     * "Good" means completed/released output, so it's the OUT-direction
     * scan_qty — falling back to IN for the (rare) operation with no OUT
     * step (routing_operation_masters.out = false), the same
     * release-direction convention BundleLedgerService::releaseInfoOf()
     * already uses.
     */
    private function goodQtyByOperationTeam($shiftTeamIds)
    {
        return DB::table('bundle_ticket_secondaries as t')
            ->join('bundle_tickets as bt', 'bt.id', '=', 't.bundle_ticket_id')
            ->join('work_order_operations as woo', 'woo.id', '=', 'bt.work_order_operation_id')
            ->join('routing_operation_masters as rom', 'rom.id', '=', 'woo.routing_operation_master_id')
            ->join('operation_masters as om', 'om.id', '=', 'rom.operation_id')
            ->join('daily_shift_teams as dst', 'dst.id', '=', 't.daily_shift_team_id')
            ->join('teams as tm', 'tm.id', '=', 'dst.team_id')
            ->where('t.active', true)
            ->whereIn('t.daily_shift_team_id', $shiftTeamIds)
            ->groupBy('om.id', 'om.operation_code', 'om.description', 'dst.id', 'tm.team_name', 'rom.out', 'bt.direction')
            ->selectRaw("
                om.id as operation_id, om.operation_code, om.description as operation_description,
                dst.id as daily_shift_team_id, tm.team_name, rom.out as has_out, bt.direction,
                SUM(t.scan_qty) as qty
            ")
            ->get()
            ->groupBy(fn ($row) => $row->operation_id . '-' . $row->daily_shift_team_id)
            ->map(function ($rowsForKey) {
                $first = $rowsForKey->first();
                $direction = $first->has_out ? 'OUT' : 'IN';
                $match = $rowsForKey->firstWhere('direction', $direction);

                return (object) [
                    'operation_id' => $first->operation_id,
                    'operation_code' => $first->operation_code,
                    'operation_description' => $first->operation_description,
                    'daily_shift_team_id' => $first->daily_shift_team_id,
                    'team_name' => $first->team_name,
                    'qty' => (int) optional($match)->qty,
                ];
            });
    }

    /**
     * This shift's scanned qty per hour, split by ticket direction (IN vs
     * OUT) — the floor screen's "throughput over the shift" line chart.
     * Clamped to "now" so the chart only grows through the shift rather
     * than showing empty hours that haven't happened yet.
     */
    private function hourlyTrend($shiftTeamIds, Carbon $shiftStart, Carbon $shiftEnd)
    {
        $rangeStart = $shiftStart;
        $rangeEnd = now()->lt($shiftEnd) ? now() : $shiftEnd;
        if ($rangeEnd->lte($rangeStart)) {
            $rangeEnd = $rangeStart->copy()->addHour();
        }

        $byHourDirection = BundleTicketSecondary::where('bundle_ticket_secondaries.active', true)
            ->whereIn('bundle_ticket_secondaries.daily_shift_team_id', $shiftTeamIds)
            ->where('bundle_ticket_secondaries.created_at', '>=', $rangeStart)
            ->where('bundle_ticket_secondaries.created_at', '<=', $rangeEnd)
            ->join('bundle_tickets as bt', 'bt.id', '=', 'bundle_ticket_secondaries.bundle_ticket_id')
            ->groupBy('hour', 'bt.direction')
            ->selectRaw("DATE_FORMAT(bundle_ticket_secondaries.created_at, '%Y-%m-%d %H:00:00') as hour, bt.direction, SUM(bundle_ticket_secondaries.scan_qty) as qty")
            ->get();

        $inByHour = $byHourDirection->where('direction', 'IN')->pluck('qty', 'hour');
        $outByHour = $byHourDirection->where('direction', 'OUT')->pluck('qty', 'hour');

        $trend = [];
        $cursor = $rangeStart->copy()->startOfHour();
        $lastHour = $rangeEnd->copy()->startOfHour();
        while ($cursor->lte($lastHour)) {
            $key = $cursor->format('Y-m-d H:00:00');
            $trend[] = [
                'hour' => $cursor->format('H:i'),
                'in_qty' => (int) ($inByHour[$key] ?? 0),
                'out_qty' => (int) ($outByHour[$key] ?? 0),
            ];
            $cursor->addHour();
        }

        return $trend;
    }

    /**
     * Same in/out-over-time data as hourlyTrend(), but broken out per team —
     * a ready-to-render heatmap grid (rows = teams, columns = hours)
     * instead of a single aggregate line, since a per-team line chart
     * doesn't scale past a couple of teams. Unlike hourlyTrend(), this
     * always covers the full shift start-to-end window (not clamped to
     * "now") so the grid can be scrolled to see the whole shift up front —
     * hours that haven't happened yet just come back zero.
     */
    private function teamHourlyTrend($shiftTeamIds, Carbon $shiftStart, Carbon $shiftEnd)
    {
        $rangeStart = $shiftStart;
        $rangeEnd = $shiftEnd;
        if ($rangeEnd->lte($rangeStart)) {
            $rangeEnd = $rangeStart->copy()->addHour();
        }

        $rows = BundleTicketSecondary::where('bundle_ticket_secondaries.active', true)
            ->whereIn('bundle_ticket_secondaries.daily_shift_team_id', $shiftTeamIds)
            ->where('bundle_ticket_secondaries.created_at', '>=', $rangeStart)
            ->where('bundle_ticket_secondaries.created_at', '<=', $rangeEnd)
            ->join('bundle_tickets as bt', 'bt.id', '=', 'bundle_ticket_secondaries.bundle_ticket_id')
            ->join('daily_shift_teams as dst', 'dst.id', '=', 'bundle_ticket_secondaries.daily_shift_team_id')
            ->join('teams as tm', 'tm.id', '=', 'dst.team_id')
            ->groupBy('hour', 'bt.direction', 'dst.id', 'tm.team_name')
            ->selectRaw("
                DATE_FORMAT(bundle_ticket_secondaries.created_at, '%Y-%m-%d %H:00:00') as hour,
                bt.direction, dst.id as daily_shift_team_id, tm.team_name,
                SUM(bundle_ticket_secondaries.scan_qty) as qty
            ")
            ->get();

        $hours = [];
        $cursor = $rangeStart->copy()->startOfHour();
        $lastHour = $rangeEnd->copy()->startOfHour();
        while ($cursor->lte($lastHour)) {
            $hours[] = $cursor->format('Y-m-d H:00:00');
            $cursor->addHour();
        }

        $teamNames = $rows->pluck('team_name', 'daily_shift_team_id');
        $teamIds = $teamNames->keys()->sort()->values();

        $qtyFor = function ($teamId, $direction) use ($rows) {
            return $rows->where('daily_shift_team_id', $teamId)->where('direction', $direction)->pluck('qty', 'hour');
        };

        $teams = $teamIds->map(function ($teamId) use ($teamNames, $qtyFor, $hours) {
            $inByHour = $qtyFor($teamId, 'IN');
            $outByHour = $qtyFor($teamId, 'OUT');

            return [
                'daily_shift_team_id' => $teamId,
                'team_name' => $teamNames[$teamId],
                'in' => collect($hours)->map(fn ($h) => (int) ($inByHour[$h] ?? 0))->values(),
                'out' => collect($hours)->map(fn ($h) => (int) ($outByHour[$h] ?? 0))->values(),
            ];
        })->values();

        return [
            'hours' => collect($hours)->map(fn ($h) => Carbon::parse($h)->format('H:i'))->values(),
            'teams' => $teams,
        ];
    }

    /**
     * Same main-model / model / batch drill-down as teamBatchSummary(), for
     * the management screen's team cards — scanned/rejected/rework by
     * batch for one team over a date range, keyed by teams.id (a team, not
     * a single day's daily_shift_team_id). No wip_qty here: queued/blocked
     * is live floor state, meaningless for a historical window.
     */
    public function teamBatchSummaryRange(Request $request): JsonResponse
    {
        try {
            $teamId = (int) $request->input('team_id');
            if (!$teamId) {
                return $this->error('team_id is required.', null, 422);
            }
            $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now();
            $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : $to->copy()->subDays(29)->startOfDay();

            $groupBy = function (string $table, string $qtyColumn) use ($from, $to, $teamId) {
                return DB::table("$table as t")
                    ->join('bundle_tickets as bt', 'bt.id', '=', 't.bundle_ticket_id')
                    ->join('bundles as b', 'b.id', '=', 'bt.bundle_id')
                    ->join('work_orders as wo', 'wo.id', '=', 'b.work_order_id')
                    ->join('batch_details as bd', 'bd.id', '=', 'wo.batch_detail_id')
                    ->join('batches as ba', 'ba.id', '=', 'bd.batch_id')
                    ->join('models as mo', 'mo.id', '=', 'bd.model_id')
                    ->leftJoin('main_models as mm', 'mm.id', '=', 'mo.main_model_id')
                    ->join('daily_shift_teams as dst', 'dst.id', '=', 't.daily_shift_team_id')
                    ->where('t.active', true)
                    ->where('t.created_at', '>=', $from)
                    ->where('t.created_at', '<=', $to)
                    ->where('dst.team_id', $teamId)
                    ->groupBy('ba.id', 'ba.batch_no', 'mo.id', 'mo.name', 'mm.id', 'mm.name')
                    ->selectRaw("
                        ba.id as batch_id, ba.batch_no,
                        mo.id as model_id, mo.name as model_name,
                        mm.id as main_model_id, mm.name as main_model_name,
                        SUM(t.$qtyColumn) as qty
                    ")
                    ->get()
                    ->keyBy(fn ($row) => $row->batch_id . '-' . $row->model_id);
            };

            $scanned = $groupBy('bundle_ticket_secondaries', 'scan_qty');
            $rejected = $groupBy('bundle_ticket_rejects', 'reject_qty');
            $reworked = $groupBy('bundle_ticket_reworks', 'rework_qty');

            $keys = $scanned->keys()->concat($rejected->keys())->concat($reworked->keys())->unique();

            $rows = $keys->map(function ($key) use ($scanned, $rejected, $reworked) {
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

            return $this->success('Team batch summary retrieved successfully.', [
                'team_id' => $teamId,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'batches' => $rows,
            ]);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve team batch summary.', $e->getMessage(), 500);
        }
    }

    /**
     * This shift's main-model / model / batch WIP breakdown for a single
     * team — the drill-down behind clicking a team card on the floor
     * screen. Scoped purely by daily_shift_team_id (no separate date
     * filter): that id already fully identifies one specific shift
     * instance, so every row against it belongs to this shift by
     * definition — no calendar cutoff needed (and one would wrongly
     * exclude an overnight shift's pre-midnight scans).
     */
    public function teamBatchSummary(Request $request): JsonResponse
    {
        try {
            $teamId = (int) $request->input('daily_shift_team_id');
            if (!$teamId) {
                return $this->error('daily_shift_team_id is required.', null, 422);
            }

            $groupBy = function (string $table, string $qtyColumn) use ($teamId) {
                return DB::table("$table as t")
                    ->join('bundle_tickets as bt', 'bt.id', '=', 't.bundle_ticket_id')
                    ->join('bundles as b', 'b.id', '=', 'bt.bundle_id')
                    ->join('work_orders as wo', 'wo.id', '=', 'b.work_order_id')
                    ->join('batch_details as bd', 'bd.id', '=', 'wo.batch_detail_id')
                    ->join('batches as ba', 'ba.id', '=', 'bd.batch_id')
                    ->join('models as mo', 'mo.id', '=', 'bd.model_id')
                    ->leftJoin('main_models as mm', 'mm.id', '=', 'mo.main_model_id')
                    ->where('t.active', true)
                    ->where('t.daily_shift_team_id', $teamId)
                    ->groupBy('ba.id', 'ba.batch_no', 'mo.id', 'mo.name', 'mm.id', 'mm.name')
                    ->selectRaw("
                        ba.id as batch_id, ba.batch_no,
                        mo.id as model_id, mo.name as model_name,
                        mm.id as main_model_id, mm.name as main_model_name,
                        SUM(t.$qtyColumn) as qty
                    ")
                    ->get()
                    ->keyBy(fn ($row) => $row->batch_id . '-' . $row->model_id);
            };

            $scanned = $groupBy('bundle_ticket_secondaries', 'scan_qty');
            $rejected = $groupBy('bundle_ticket_rejects', 'reject_qty');
            $reworked = $groupBy('bundle_ticket_reworks', 'rework_qty');
            $reworkResolved = $groupBy('bundle_ticket_rework_returns', 'return_qty + reject_qty');

            // Same scanned total split by direction as teamScoreboard(), so
            // this batch-level wip_qty nets IN against OUT instead of
            // adding them together.
            $scannedByDirection = DB::table('bundle_ticket_secondaries as t')
                ->join('bundle_tickets as bt', 'bt.id', '=', 't.bundle_ticket_id')
                ->join('bundles as b', 'b.id', '=', 'bt.bundle_id')
                ->join('work_orders as wo', 'wo.id', '=', 'b.work_order_id')
                ->join('batch_details as bd', 'bd.id', '=', 'wo.batch_detail_id')
                ->join('batches as ba', 'ba.id', '=', 'bd.batch_id')
                ->join('models as mo', 'mo.id', '=', 'bd.model_id')
                ->where('t.active', true)
                ->where('t.daily_shift_team_id', $teamId)
                ->groupBy('ba.id', 'mo.id', 'bt.direction')
                ->selectRaw('ba.id as batch_id, mo.id as model_id, bt.direction, SUM(t.scan_qty) as qty')
                ->get();
            $keyer = fn ($row) => $row->batch_id . '-' . $row->model_id;
            $scannedIn = $scannedByDirection->where('direction', 'IN')->keyBy($keyer);
            $scannedOut = $scannedByDirection->where('direction', 'OUT')->keyBy($keyer);

            $keys = $scanned->keys()->concat($rejected->keys())->concat($reworked->keys())->unique();

            $rows = $keys->map(function ($key) use ($scanned, $rejected, $reworked, $reworkResolved, $scannedIn, $scannedOut) {
                $row = $scanned[$key] ?? $rejected[$key] ?? $reworked[$key];
                $scannedQty = (int) optional($scanned[$key] ?? null)->qty;
                $rejectedQty = (int) optional($rejected[$key] ?? null)->qty;
                $reworkQty = (int) optional($reworked[$key] ?? null)->qty;
                $reworkOutstanding = max(0, $reworkQty - (int) optional($reworkResolved[$key] ?? null)->qty);
                $handled = $scannedQty + $rejectedQty + $reworkQty;
                $scannedInQty = (int) optional($scannedIn[$key] ?? null)->qty;
                $scannedOutQty = (int) optional($scannedOut[$key] ?? null)->qty;

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
                    'wip_qty' => max(0, $scannedInQty - $scannedOutQty - $rejectedQty - $reworkOutstanding),
                    'reject_rate_pct' => $handled > 0 ? round($rejectedQty / $handled * 100, 1) : 0,
                    'rework_rate_pct' => $handled > 0 ? round($reworkQty / $handled * 100, 1) : 0,
                ];
            })->sortByDesc('scanned_qty')->values();

            return $this->success('Team batch summary retrieved successfully.', [
                'daily_shift_team_id' => $teamId,
                'batches' => $rows,
            ]);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve team batch summary.', $e->getMessage(), 500);
        }
    }

    /**
     * Bundle-level detail for one (batch, model) pair, floor-wide — the
     * drill-down behind clicking a row in the whole-floor batch summary.
     * Only bundles with something still outstanding on their route are
     * included, same "what's actually on the floor right now" contract as
     * floor()'s own batch_breakdown.
     */
    public function batchBundles(Request $request): JsonResponse
    {
        try {
            $batchId = (int) $request->input('batch_id');
            $modelId = (int) $request->input('model_id');
            if (!$batchId || !$modelId) {
                return $this->error('batch_id and model_id are required.', null, 422);
            }

            $bundles = [];
            Bundle::where('active', true)
                ->whereHas('workOrder.batchDetail', function ($q) use ($batchId, $modelId) {
                    $q->where('batch_id', $batchId)->where('model_id', $modelId);
                })
                ->with(['workOrder.batchDetail.batch', 'workOrder.batchDetail.model.mainModel'])
                ->orderBy('id')
                ->chunk(50, function ($chunk) use (&$bundles) {
                    foreach ($chunk as $bundle) {
                        $this->accumulateBundleDetail($bundle, $bundles);
                    }
                });

            return $this->success('Batch bundle details retrieved successfully.', [
                'batch_id' => $batchId,
                'model_id' => $modelId,
                'bundles' => array_values($bundles),
            ]);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve batch bundle details.', $e->getMessage(), 500);
        }
    }

    /**
     * One bundle's ticket-level ledger state, for the batch-bundle
     * drill-down — mirrors accumulateFloorData()'s per-ticket math but
     * reports per-bundle instead of rolling into station/batch totals.
     */
    private function accumulateBundleDetail(Bundle $bundle, array &$bundles): void
    {
        $ledger = $this->ledger->build($bundle);
        $workOrderOperations = $ledger['workOrderOperations'];
        if ($workOrderOperations->every($ledger['resolved'])) {
            return;
        }

        $tickets = $ledger['tickets'];
        if ($tickets->isEmpty()) {
            return;
        }

        $ticketRemaining = $ledger['ticketRemaining'];
        $availableNow = $ledger['availableNow'];
        $outstandingRework = $ledger['outstandingRework'];

        $ticketRows = [];
        $queuedQty = 0;
        $blockedQty = 0;
        $reworkQty = 0;

        foreach ($tickets as $ticket) {
            $woo = $ticket->workOrderOperation;
            $rom = optional($woo)->routingOperationMaster;
            if (!$rom) {
                continue;
            }

            $remaining = $ticketRemaining($ticket);
            $available = $availableNow($ticket);
            $rework = $outstandingRework($ticket);
            $blocked = max(0, $remaining - $available);

            if ($remaining <= 0 && $rework <= 0) {
                continue;
            }

            $queuedQty += $available;
            $blockedQty += $blocked;
            $reworkQty += $rework;

            $ticketRows[] = [
                'bundle_ticket_id' => $ticket->id,
                'operation_code' => optional($rom->operation)->operation_code,
                'operation_description' => optional($rom->operation)->description,
                'seq' => (int) $rom->seq,
                'direction' => $ticket->direction,
                'queued_qty' => $available,
                'blocked_qty' => $blocked,
                'outstanding_rework' => $rework,
            ];
        }

        if (empty($ticketRows)) {
            return;
        }

        $batchDetail = optional($bundle->workOrder)->batchDetail;
        $modelRecord = optional($batchDetail)->model;
        $mainModelRecord = optional($modelRecord)->mainModel;

        $bundles[$bundle->id] = [
            'bundle_id' => $bundle->id,
            'work_order_id' => $bundle->work_order_id,
            'qty' => $bundle->qty,
            'size' => $bundle->size,
            'batch_no' => optional(optional($batchDetail)->batch)->batch_no,
            'model_name' => optional($modelRecord)->name,
            'main_model_name' => optional($mainModelRecord)->name,
            'queued_qty' => $queuedQty,
            'blocked_qty' => $blockedQty,
            'outstanding_rework' => $reworkQty,
            'tickets' => $ticketRows,
        ];
    }

    /**
     * Management trend view: the same shape of data as the floor screen
     * (team totals, output by operation & team, in/out over time, batch
     * breakdown) but rolled up over a selected date range instead of the
     * current shift — no live "problem alerts" here, since those describe
     * what's stuck on the floor right now, not a historical window.
     */
    public function management(Request $request): JsonResponse
    {
        try {
            $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now();
            if ($request->filled('from')) {
                $from = Carbon::parse($request->input('from'))->startOfDay();
            } elseif ($request->filled('days')) {
                $days = max(1, min((int) $request->input('days'), 366));
                $from = $to->copy()->subDays($days - 1)->startOfDay();
            } else {
                $from = $to->copy()->subDays(29)->startOfDay();
            }

            if ($from->gt($to)) {
                return $this->error('The "from" date must not be after the "to" date.', null, 422);
            }

            // Every DailyShiftTeam under a shift whose window overlaps
            // [from, to] — the range equivalent of floor()'s
            // currentShiftScope(), and what every query below is scoped to
            // instead of a bare created_at cutoff.
            $shiftTeamIds = $this->shiftTeamIdsForRange($from, $to);

            $scannedByDay = BundleTicketSecondary::where('active', true)
                ->whereIn('daily_shift_team_id', $shiftTeamIds)
                ->where('created_at', '>=', $from)->where('created_at', '<=', $to)
                ->selectRaw('DATE(created_at) as day, SUM(scan_qty) as qty')
                ->groupBy('day')
                ->pluck('qty', 'day');
            $rejectedByDay = BundleTicketReject::where('active', true)
                ->whereIn('daily_shift_team_id', $shiftTeamIds)
                ->where('created_at', '>=', $from)->where('created_at', '<=', $to)
                ->selectRaw('DATE(created_at) as day, SUM(reject_qty) as qty')
                ->groupBy('day')
                ->pluck('qty', 'day');
            $reworkByDay = BundleTicketRework::where('active', true)
                ->whereIn('daily_shift_team_id', $shiftTeamIds)
                ->where('created_at', '>=', $from)->where('created_at', '<=', $to)
                ->selectRaw('DATE(created_at) as day, SUM(rework_qty) as qty')
                ->groupBy('day')
                ->pluck('qty', 'day');

            $daily = [];
            $cursor = $from->copy();
            $lastDay = $to->copy()->startOfDay();
            while ($cursor->lte($lastDay)) {
                $date = $cursor->toDateString();
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
                $cursor->addDay();
            }

            $totalScanned = array_sum(array_column($daily, 'scanned_qty'));
            $totalRejected = array_sum(array_column($daily, 'rejected_qty'));
            $totalRework = array_sum(array_column($daily, 'rework_qty'));
            $totalHandled = $totalScanned + $totalRejected + $totalRework;

            return $this->success('Management dashboard retrieved successfully.', [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'summary' => [
                    'total_scanned' => $totalScanned,
                    'total_rejected' => $totalRejected,
                    'total_rework_sent' => $totalRework,
                    'overall_reject_rate_pct' => $totalHandled > 0 ? round($totalRejected / $totalHandled * 100, 1) : 0,
                    'overall_rework_rate_pct' => $totalHandled > 0 ? round($totalRework / $totalHandled * 100, 1) : 0,
                ],
                'daily' => $daily,
                'batch_breakdown' => $this->batchBreakdown($from, $to, $shiftTeamIds),
                'team_scoreboard' => $this->teamScoreboardRange($from, $to, $shiftTeamIds),
                'station_team_breakdown' => $this->stationTeamBreakdownRange($from, $to, $shiftTeamIds),
                'team_daily_trend' => $this->teamDailyTrend($from, $to, $shiftTeamIds),
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
    private function batchBreakdown($from, $to, $shiftTeamIds)
    {
        $scanned = $this->batchQtyByGroup('bundle_ticket_secondaries', 'scan_qty', $from, $to, $shiftTeamIds);
        $rejected = $this->batchQtyByGroup('bundle_ticket_rejects', 'reject_qty', $from, $to, $shiftTeamIds);
        $reworked = $this->batchQtyByGroup('bundle_ticket_reworks', 'rework_qty', $from, $to, $shiftTeamIds);

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
    private function batchQtyByGroup(string $table, string $qtyColumn, $from, $to, $shiftTeamIds)
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
            ->whereIn('t.daily_shift_team_id', $shiftTeamIds)
            ->where('t.created_at', '>=', $from)
            ->where('t.created_at', '<=', $to)
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

    /**
     * Same per-team scanned/rejected/rework/WIP/efficiency shape as the
     * floor screen's teamScoreboard(), but rolled up by the underlying
     * team (teams.id) over a date range instead of by a single day's
     * daily_shift_team_id — a team has one such row per shift-day, so
     * the range view has to fold all of them back into one card per team.
     */
    private function teamScoreboardRange(Carbon $from, Carbon $to, $shiftTeamIds)
    {
        $groupByTeam = function (string $table, string $qtyColumn) use ($from, $to, $shiftTeamIds) {
            return DB::table("$table as t")
                ->join('daily_shift_teams as dst', 'dst.id', '=', 't.daily_shift_team_id')
                ->where('t.active', true)
                ->whereIn('t.daily_shift_team_id', $shiftTeamIds)
                ->where('t.created_at', '>=', $from)
                ->where('t.created_at', '<=', $to)
                ->groupBy('dst.team_id')
                ->selectRaw("dst.team_id, SUM(t.$qtyColumn) as qty")
                ->pluck('qty', 'team_id');
        };

        // Split by bundle_tickets.direction — same reasoning as
        // teamScoreboard()'s $scannedByDirection: a team's operation
        // routinely has both an IN ticket and an OUT ticket, so the total
        // must be netted (IN - OUT), not summed, to get a WIP figure.
        $scannedByDirection = DB::table('bundle_ticket_secondaries as t')
            ->join('daily_shift_teams as dst', 'dst.id', '=', 't.daily_shift_team_id')
            ->join('bundle_tickets as bt', 'bt.id', '=', 't.bundle_ticket_id')
            ->where('t.active', true)
            ->whereIn('t.daily_shift_team_id', $shiftTeamIds)
            ->where('t.created_at', '>=', $from)
            ->where('t.created_at', '<=', $to)
            ->groupBy('dst.team_id', 'bt.direction')
            ->selectRaw('dst.team_id, bt.direction, SUM(t.scan_qty) as qty')
            ->get();
        $scannedIn = $scannedByDirection->where('direction', 'IN')->pluck('qty', 'team_id');
        $scannedOut = $scannedByDirection->where('direction', 'OUT')->pluck('qty', 'team_id');
        $rejected = $groupByTeam('bundle_ticket_rejects', 'reject_qty');
        $reworked = $groupByTeam('bundle_ticket_reworks', 'rework_qty');
        $reworkResolved = DB::table('bundle_ticket_rework_returns as t')
            ->join('daily_shift_teams as dst', 'dst.id', '=', 't.daily_shift_team_id')
            ->where('t.active', true)
            ->whereIn('t.daily_shift_team_id', $shiftTeamIds)
            ->where('t.created_at', '>=', $from)
            ->where('t.created_at', '<=', $to)
            ->groupBy('dst.team_id')
            ->selectRaw('dst.team_id, SUM(t.return_qty + t.reject_qty) as qty')
            ->pluck('qty', 'team_id');
        $earnedMinutesByTeam = DB::table('bundle_ticket_secondaries as bts')
            ->join('bundle_tickets as bt', 'bt.id', '=', 'bts.bundle_ticket_id')
            ->join('work_order_operations as woo', 'woo.id', '=', 'bt.work_order_operation_id')
            ->join('daily_shift_teams as dst', 'dst.id', '=', 'bts.daily_shift_team_id')
            ->where('bts.active', true)
            ->whereIn('bts.daily_shift_team_id', $shiftTeamIds)
            ->where('bts.created_at', '>=', $from)
            ->where('bts.created_at', '<=', $to)
            ->groupBy('dst.team_id')
            ->selectRaw('dst.team_id, SUM(bts.scan_qty * woo.smv) as minutes')
            ->pluck('minutes', 'team_id');

        $teamIds = $scannedIn->keys()->concat($scannedOut->keys())->concat($rejected->keys())->concat($reworked->keys())->unique();
        if ($teamIds->isEmpty()) {
            return collect();
        }

        // Available operator-minutes per team: every shift assignment that
        // overlaps the window, each clamped to [from, to] (and to "now",
        // for a window that runs into today) — needs per-row clamping so
        // it's folded in PHP rather than a pure SQL sum.
        $now = now();
        $availableMinutesByTeam = [];
        DailyShiftTeam::with('dailyShift')->whereIn('team_id', $teamIds)->get()->each(function ($dst) use ($from, $to, $now, &$availableMinutesByTeam) {
            $start = $dst->start_date_time ?: optional($dst->dailyShift)->start_date_time;
            $end = $dst->end_date_time ?: optional($dst->dailyShift)->end_date_time;
            if (!$start || !$end || $start->gt($to) || $end->lt($from)) {
                return;
            }
            $clampedStart = $start->max($from);
            $clampedEnd = $end->min($to)->min($now);
            if ($clampedEnd->lte($clampedStart)) {
                return;
            }
            $minutes = $clampedStart->diffInMinutes($clampedEnd) * (int) ($dst->no_of_operators ?? 0);
            $availableMinutesByTeam[$dst->team_id] = ($availableMinutesByTeam[$dst->team_id] ?? 0) + $minutes;
        });

        $teams = Team::with('operation')->whereIn('id', $teamIds)->get()->keyBy('id');

        return $teamIds->map(function ($teamId) use ($scannedIn, $scannedOut, $rejected, $reworked, $reworkResolved, $earnedMinutesByTeam, $availableMinutesByTeam, $teams) {
            $scannedInQty = (int) ($scannedIn[$teamId] ?? 0);
            $scannedOutQty = (int) ($scannedOut[$teamId] ?? 0);
            $scannedQty = $scannedInQty + $scannedOutQty;
            $rejectedQty = (int) ($rejected[$teamId] ?? 0);
            $reworkQty = (int) ($reworked[$teamId] ?? 0);
            $reworkOutstanding = max(0, $reworkQty - (int) ($reworkResolved[$teamId] ?? 0));
            $handled = $scannedQty + $rejectedQty + $reworkQty;
            $team = $teams[$teamId] ?? null;

            $earned = (float) ($earnedMinutesByTeam[$teamId] ?? 0);
            $available = (float) ($availableMinutesByTeam[$teamId] ?? 0);

            return [
                'team_id' => $teamId,
                'team_name' => optional($team)->team_name,
                'operation_id' => optional($team)->operation_id,
                'operation_code' => optional(optional($team)->operation)->operation_code,
                'operation_description' => optional(optional($team)->operation)->description,
                'scanned_qty' => $scannedQty,
                'rejected_qty' => $rejectedQty,
                'rework_qty' => $reworkQty,
                'wip_qty' => max(0, $scannedInQty - $scannedOutQty - $rejectedQty - $reworkOutstanding),
                'reject_rate_pct' => $handled > 0 ? round($rejectedQty / $handled * 100, 1) : 0,
                'rework_rate_pct' => $handled > 0 ? round($reworkQty / $handled * 100, 1) : 0,
                'efficiency_pct' => $available > 0 ? round($earned / $available * 100, 1) : 0,
            ];
        })->sortByDesc('scanned_qty')->values();
    }

    /**
     * Same good/reject/rework by (operation, team) shape as the floor
     * screen's stationTeamBreakdown(), grouped by the underlying team
     * (teams.id) over a date range instead of a single day's
     * daily_shift_team_id.
     */
    private function stationTeamBreakdownRange(Carbon $from, Carbon $to, $shiftTeamIds)
    {
        $rows = function (string $table, string $qtyColumn) use ($from, $to, $shiftTeamIds) {
            return DB::table("$table as t")
                ->join('bundle_tickets as bt', 'bt.id', '=', 't.bundle_ticket_id')
                ->join('work_order_operations as woo', 'woo.id', '=', 'bt.work_order_operation_id')
                ->join('routing_operation_masters as rom', 'rom.id', '=', 'woo.routing_operation_master_id')
                ->join('operation_masters as om', 'om.id', '=', 'rom.operation_id')
                ->join('daily_shift_teams as dst', 'dst.id', '=', 't.daily_shift_team_id')
                ->join('teams as tm', 'tm.id', '=', 'dst.team_id')
                ->where('t.active', true)
                ->whereIn('t.daily_shift_team_id', $shiftTeamIds)
                ->where('t.created_at', '>=', $from)
                ->where('t.created_at', '<=', $to)
                ->groupBy('om.id', 'om.operation_code', 'om.description', 'tm.id', 'tm.team_name')
                ->selectRaw("
                    om.id as operation_id, om.operation_code, om.description as operation_description,
                    tm.id as team_id, tm.team_name,
                    SUM(t.$qtyColumn) as qty
                ")
                ->get()
                ->keyBy(fn ($row) => $row->operation_id . '-' . $row->team_id);
        };

        $good = $this->goodQtyByOperationTeamRange($from, $to, $shiftTeamIds);
        $reject = $rows('bundle_ticket_rejects', 'reject_qty');
        $rework = $rows('bundle_ticket_reworks', 'rework_qty');

        $keys = $good->keys()->concat($reject->keys())->concat($rework->keys())->unique();

        return $keys->map(function ($key) use ($good, $reject, $rework) {
            $row = $good[$key] ?? $reject[$key] ?? $rework[$key];

            return [
                'operation_id' => $row->operation_id,
                'operation_code' => $row->operation_code,
                'operation_description' => $row->operation_description,
                'team_id' => $row->team_id,
                'team_name' => $row->team_name,
                'good_qty' => (int) optional($good[$key] ?? null)->qty,
                'reject_qty' => (int) optional($reject[$key] ?? null)->qty,
                'rework_qty' => (int) optional($rework[$key] ?? null)->qty,
            ];
        })->sortBy(['operation_code', 'team_name'])->values();
    }

    /**
     * Range version of goodQtyByOperationTeam() — same OUT-with-IN-fallback
     * release-direction logic, grouped by the underlying team (teams.id)
     * over [from, to] instead of a single day's daily_shift_team_id.
     */
    private function goodQtyByOperationTeamRange(Carbon $from, Carbon $to, $shiftTeamIds)
    {
        return DB::table('bundle_ticket_secondaries as t')
            ->join('bundle_tickets as bt', 'bt.id', '=', 't.bundle_ticket_id')
            ->join('work_order_operations as woo', 'woo.id', '=', 'bt.work_order_operation_id')
            ->join('routing_operation_masters as rom', 'rom.id', '=', 'woo.routing_operation_master_id')
            ->join('operation_masters as om', 'om.id', '=', 'rom.operation_id')
            ->join('daily_shift_teams as dst', 'dst.id', '=', 't.daily_shift_team_id')
            ->join('teams as tm', 'tm.id', '=', 'dst.team_id')
            ->where('t.active', true)
            ->whereIn('t.daily_shift_team_id', $shiftTeamIds)
            ->where('t.created_at', '>=', $from)
            ->where('t.created_at', '<=', $to)
            ->groupBy('om.id', 'om.operation_code', 'om.description', 'tm.id', 'tm.team_name', 'rom.out', 'bt.direction')
            ->selectRaw("
                om.id as operation_id, om.operation_code, om.description as operation_description,
                tm.id as team_id, tm.team_name, rom.out as has_out, bt.direction,
                SUM(t.scan_qty) as qty
            ")
            ->get()
            ->groupBy(fn ($row) => $row->operation_id . '-' . $row->team_id)
            ->map(function ($rowsForKey) {
                $first = $rowsForKey->first();
                $direction = $first->has_out ? 'OUT' : 'IN';
                $match = $rowsForKey->firstWhere('direction', $direction);

                return (object) [
                    'operation_id' => $first->operation_id,
                    'operation_code' => $first->operation_code,
                    'operation_description' => $first->operation_description,
                    'team_id' => $first->team_id,
                    'team_name' => $first->team_name,
                    'qty' => (int) optional($match)->qty,
                ];
            });
    }

    /**
     * Same in/out heatmap-grid shape as the floor screen's
     * teamHourlyTrend(), at daily instead of hourly granularity — a
     * multi-day range makes an hour axis meaningless, so each column here
     * is a calendar day instead of an hour.
     */
    private function teamDailyTrend(Carbon $from, Carbon $to, $shiftTeamIds)
    {
        $rows = BundleTicketSecondary::where('bundle_ticket_secondaries.active', true)
            ->whereIn('bundle_ticket_secondaries.daily_shift_team_id', $shiftTeamIds)
            ->where('bundle_ticket_secondaries.created_at', '>=', $from)
            ->where('bundle_ticket_secondaries.created_at', '<=', $to)
            ->join('bundle_tickets as bt', 'bt.id', '=', 'bundle_ticket_secondaries.bundle_ticket_id')
            ->join('daily_shift_teams as dst', 'dst.id', '=', 'bundle_ticket_secondaries.daily_shift_team_id')
            ->join('teams as tm', 'tm.id', '=', 'dst.team_id')
            ->groupBy('day', 'bt.direction', 'tm.id', 'tm.team_name')
            ->selectRaw("
                DATE(bundle_ticket_secondaries.created_at) as day, bt.direction,
                tm.id as team_id, tm.team_name, SUM(bundle_ticket_secondaries.scan_qty) as qty
            ")
            ->get();

        $days = [];
        $cursor = $from->copy()->startOfDay();
        $lastDay = $to->copy()->startOfDay();
        while ($cursor->lte($lastDay)) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }

        $teamNames = $rows->pluck('team_name', 'team_id');
        $teamIds = $teamNames->keys()->sort()->values();

        $qtyFor = function ($teamId, $direction) use ($rows) {
            return $rows->where('team_id', $teamId)->where('direction', $direction)->pluck('qty', 'day');
        };

        $teams = $teamIds->map(function ($teamId) use ($teamNames, $qtyFor, $days) {
            $inByDay = $qtyFor($teamId, 'IN');
            $outByDay = $qtyFor($teamId, 'OUT');

            return [
                'team_id' => $teamId,
                'team_name' => $teamNames[$teamId],
                'in' => collect($days)->map(fn ($d) => (int) ($inByDay[$d] ?? 0))->values(),
                'out' => collect($days)->map(fn ($d) => (int) ($outByDay[$d] ?? 0))->values(),
            ];
        })->values();

        return [
            'days' => collect($days)->map(fn ($d) => Carbon::parse($d)->format('M d'))->values(),
            'teams' => $teams,
        ];
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
