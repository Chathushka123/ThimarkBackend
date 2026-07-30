<?php

namespace App\Http\Repositories;

use App\Models\Bundle;
use App\Models\BundleTicket;
use App\Models\BundleTicketReject;
use App\Models\BundleTicketRework;
use App\Models\BundleTicketReworkReturn;
use App\Models\BundleTicketSecondary;

/**
 * One row per bundle: batch/model lineage, trolley, and IN/OUT/WIP for every
 * operation that appears across the reported bundles' routes - operations
 * outside a given bundle's own route (or a direction a route doesn't track)
 * are marked NA (null).
 *
 * This report is a dynamic pivot - the column set depends on which routes
 * appear in the result set - so unlike the other reports in this app (a
 * single flat raw SQL query per query-type) it's assembled with Eloquent +
 * PHP, one pass per bundle.
 */
class WorkOrderStatusReportRepository
{
    public function getReport(?string $status = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $bundlesQuery = Bundle::with([
            'workOrder.batchDetail.batch',
            'workOrder.batchDetail.model.mainModel',
            'workOrder.workOrderOperations' => function ($query) {
                $query->where('active', true)->with('routingOperationMaster.operation');
            },
            'trollyMaster',
        ])->where('active', true);

        $bundlesQuery->whereHas('workOrder', function ($query) use ($status, $fromDate, $toDate) {
            if ($status) {
                $query->where('status', strtoupper($status));
            }
            if ($fromDate) {
                $query->whereDate('updated_at', '>=', $fromDate);
            }
            if ($toDate) {
                $query->whereDate('updated_at', '<=', $toDate);
            }
        });

        $bundles = $bundlesQuery->orderBy('work_order_id')->orderBy('id')->get();

        if ($bundles->isEmpty()) {
            return ['operations' => [], 'data' => []];
        }

        // Global, ordered operation list - the union of every operation
        // appearing across every reported bundle's route - plus, per
        // bundle, which work_order_operation (and in/out flags) covers it.
        $operationsById = [];
        $bundleOperationMap = [];

        foreach ($bundles as $bundle) {
            $workOrder = $bundle->workOrder;
            $map = [];

            if ($workOrder) {
                foreach ($workOrder->workOrderOperations as $woo) {
                    $rom = $woo->routingOperationMaster;
                    $operation = $rom ? $rom->operation : null;

                    if (!$rom || !$operation) {
                        continue;
                    }

                    if (!isset($operationsById[$operation->id])) {
                        $operationsById[$operation->id] = [
                            'id' => $operation->id,
                            'code' => $operation->operation_code,
                            'description' => $operation->description,
                            'key' => strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $operation->operation_code)),
                            'min_seq' => (int) $rom->seq,
                        ];
                    } else {
                        $operationsById[$operation->id]['min_seq'] = min(
                            $operationsById[$operation->id]['min_seq'],
                            (int) $rom->seq
                        );
                    }

                    $map[$operation->id] = [
                        'woo_id' => $woo->id,
                        'in' => (bool) $rom->in,
                        'out' => (bool) $rom->out,
                    ];
                }
            }

            $bundleOperationMap[$bundle->id] = $map;
        }

        $operations = array_values($operationsById);
        usort($operations, function ($a, $b) {
            return $a['min_seq'] === $b['min_seq']
                ? strcmp($a['code'], $b['code'])
                : $a['min_seq'] <=> $b['min_seq'];
        });

        // Every active bundle_ticket for these bundles, keyed by
        // "{bundle_id}:{work_order_operation_id}:{direction}" so we can look
        // up the right ticket for a given bundle + operation + direction.
        $bundleIds = $bundles->pluck('id')->all();
        $tickets = BundleTicket::where('active', true)->whereIn('bundle_id', $bundleIds)->get();

        $ticketByKey = [];
        foreach ($tickets as $ticket) {
            $ticketByKey["{$ticket->bundle_id}:{$ticket->work_order_operation_id}:{$ticket->direction}"] = $ticket->id;
        }

        $ticketIds = $tickets->pluck('id')->all();
        $ticketToBundle = $tickets->pluck('bundle_id', 'id');

        // Scanned (good) qty per ticket - what IN/OUT actually track.
        $scannedByTicket = BundleTicketSecondary::whereIn('bundle_ticket_id', $ticketIds)
            ->where('active', true)
            ->groupBy('bundle_ticket_id')
            ->selectRaw('bundle_ticket_id, SUM(scan_qty) as total')
            ->pluck('total', 'bundle_ticket_id');

        // Reject qty per ticket - covers BOTH direct rejects and post-rework
        // rejects, since a rework return records its reject slice as an
        // ordinary bundle_ticket_rejects row (source=REWORK_REJECT) against
        // this same ticket - see BundleTicketReworkReturn's docblock.
        $rejectByTicket = BundleTicketReject::whereIn('bundle_ticket_id', $ticketIds)
            ->where('active', true)
            ->groupBy('bundle_ticket_id')
            ->selectRaw('bundle_ticket_id, SUM(reject_qty) as total')
            ->pluck('total', 'bundle_ticket_id');

        // Rework qty sent (gross - not netted against returns) and rework
        // return qty (return_qty + reject_qty - everything the rework team
        // has resolved, whether it came back good or was rejected after
        // rework) per ticket.
        $reworkSentByTicket = BundleTicketRework::whereIn('bundle_ticket_id', $ticketIds)
            ->where('active', true)
            ->groupBy('bundle_ticket_id')
            ->selectRaw('bundle_ticket_id, SUM(rework_qty) as total')
            ->pluck('total', 'bundle_ticket_id');
        $reworkReturnByTicket = BundleTicketReworkReturn::whereIn('bundle_ticket_id', $ticketIds)
            ->where('active', true)
            ->groupBy('bundle_ticket_id')
            ->selectRaw('bundle_ticket_id, SUM(return_qty + reject_qty) as total')
            ->pluck('total', 'bundle_ticket_id');

        // Reject/rework/return totals, rolled up per bundle across every one
        // of its tickets (i.e. across all of that bundle's operations) -
        // informational summary columns only, not used in the per-operation
        // WIP calculation below.
        $rejectByBundle = $this->sumGroupedByBundle(
            BundleTicketReject::whereIn('bundle_ticket_id', $ticketIds)->where('active', true),
            'reject_qty',
            $ticketToBundle
        );
        $reworkByBundle = $this->sumGroupedByBundle(
            BundleTicketRework::whereIn('bundle_ticket_id', $ticketIds)->where('active', true),
            'rework_qty',
            $ticketToBundle
        );
        $returnByBundle = $this->sumGroupedByBundle(
            BundleTicketReworkReturn::whereIn('bundle_ticket_id', $ticketIds)->where('active', true),
            'return_qty + reject_qty',
            $ticketToBundle
        );

        $data = [];

        foreach ($bundles as $bundle) {
            $workOrder = $bundle->workOrder;
            $batchDetail = optional($workOrder)->batchDetail;
            $batch = optional($batchDetail)->batch;
            $model = optional($batchDetail)->model;
            $mainModel = optional($model)->mainModel;
            $trolly = $bundle->trollyMaster;

            $row = [
                'main_model' => optional($mainModel)->name,
                'model' => optional($model)->name,
                'batch' => optional($batch)->batch_no,
                'job_id' => optional($workOrder)->id,
                'bundle_id' => $bundle->id,
                'trolley' => optional($trolly)->code,
            ];

            $applicableOps = $bundleOperationMap[$bundle->id] ?? [];

            foreach ($operations as $operation) {
                $key = $operation['key'];
                $opInfo = $applicableOps[$operation['id']] ?? null;

                if ($opInfo === null) {
                    // This operation isn't part of this bundle's route at all.
                    $row["{$key}_IN"] = null;
                    $row["{$key}_OUT"] = null;
                    $row["{$key}_WIP"] = null;
                    continue;
                }

                $wooId = $opInfo['woo_id'];

                if ($opInfo['in']) {
                    $inTicketId = $ticketByKey["{$bundle->id}:{$wooId}:IN"] ?? null;
                    $inQty = (int) ($scannedByTicket[$inTicketId] ?? 0);
                } else {
                    // Route doesn't track an IN step for this operation.
                    $inQty = null;
                }

                if ($opInfo['out']) {
                    $outTicketId = $ticketByKey["{$bundle->id}:{$wooId}:OUT"] ?? null;
                    $outQty = (int) ($scannedByTicket[$outTicketId] ?? 0);
                    $outRejectQty = (int) ($rejectByTicket[$outTicketId] ?? 0);
                    $outReworkQty = (int) ($reworkSentByTicket[$outTicketId] ?? 0);
                    $outReworkReturnQty = (int) ($reworkReturnByTicket[$outTicketId] ?? 0);
                } else {
                    $outQty = null;
                    $outRejectQty = 0;
                    $outReworkQty = 0;
                    $outReworkReturnQty = 0;
                }

                $row["{$key}_IN"] = $inQty;
                $row["{$key}_OUT"] = $outQty;
                // WIP still sitting at this operation: what came IN, minus
                // what's gone OUT as good, minus rejects, minus everything
                // sent to rework, plus back whatever rework has already
                // resolved (returned good or rejected after rework) - what's
                // left unadded is exactly the rework qty still outstanding.
                $row["{$key}_WIP"] = ($inQty !== null && $outQty !== null)
                    ? max(0, $inQty - $outQty - $outRejectQty - $outReworkQty + $outReworkReturnQty)
                    : null;
            }

            $row['total_reject_qty'] = (int) ($rejectByBundle[$bundle->id] ?? 0);
            $row['total_rework_qty'] = (int) ($reworkByBundle[$bundle->id] ?? 0);
            $row['total_return_qty'] = (int) ($returnByBundle[$bundle->id] ?? 0);

            $data[] = $row;
        }

        return [
            'operations' => array_map(function ($op) {
                return ['code' => $op['code'], 'description' => $op['description'], 'key' => $op['key']];
            }, $operations),
            'data' => $data,
        ];
    }

    /**
     * Sum $qtyColumn on $query (already filtered to the relevant ticket ids)
     * grouped by bundle_ticket_id, then roll that up to bundle_id via the
     * ticket->bundle map.
     *
     * @return array<int, int> bundle_id => total
     */
    private function sumGroupedByBundle($query, string $qtyColumn, $ticketToBundle): array
    {
        $totalsByTicket = $query->groupBy('bundle_ticket_id')
            ->selectRaw("bundle_ticket_id, SUM({$qtyColumn}) as total")
            ->pluck('total', 'bundle_ticket_id');

        $result = [];
        foreach ($totalsByTicket as $ticketId => $total) {
            $bundleId = $ticketToBundle[$ticketId] ?? null;
            if ($bundleId === null) {
                continue;
            }
            $result[$bundleId] = ($result[$bundleId] ?? 0) + (int) $total;
        }

        return $result;
    }
}
