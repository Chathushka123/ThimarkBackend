<?php

namespace App\Http\Repositories;

use App\BatchDetail;
use App\Models\Bundle;
use App\Models\BundleDetail;
use App\Models\BundleTicket;
use App\Models\BundleTicketReject;
use App\Models\BundleTicketSecondary;
use App\Models\WorkOrder;
use App\Models\WorkOrderOperation;
use App\RoutingOperationMaster;
use App\TrollyMaster;
use App\WhlItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkOrderRepository
{
    /**
     * Batch details available to pick from when creating a work order.
     */
    public function batchDetailsList()
    {
        $batchDetails = BatchDetail::with(['batch', 'model.mainModel', 'model.routeMaster'])
            ->where('active', true)
            ->orderByDesc('id')
            ->get();

        if ($batchDetails->isEmpty()) {
            return $batchDetails;
        }

        $this->assignAvailableQty($batchDetails);

        return $batchDetails->reject(fn ($batchDetail) => $batchDetail->available_qty === 0)->values();
    }

    /**
     * Compute and attach total_bundle_qty / total_reject_qty / available_qty
     * onto each given batch detail (mutates in place). available_qty is how
     * much of the batch's quantity hasn't yet been committed to a bundle
     * across ANY of that batch detail's work orders - rejected qty is added
     * back since it frees up room to re-pick.
     */
    private function assignAvailableQty($batchDetails): void
    {
        $batchDetailIds = $batchDetails->pluck('id')->all();

        // Total qty already committed to bundles, per batch detail (across
        // all of that batch detail's work orders).
        $bundleQtyByBatchDetail = Bundle::query()
            ->join('work_orders', 'work_orders.id', '=', 'bundles.work_order_id')
            ->where('bundles.active', true)
            ->where('work_orders.active', true)
            ->whereIn('work_orders.batch_detail_id', $batchDetailIds)
            ->groupBy('work_orders.batch_detail_id')
            ->selectRaw('work_orders.batch_detail_id, SUM(bundles.qty) as total')
            ->pluck('total', 'batch_detail_id');

        // Total rejected qty across every bundle ticket of every bundle of
        // every work order under a batch detail - rejects free up qty that
        // can be re-picked into a new bundle.
        $rejectQtyByBatchDetail = BundleTicketReject::query()
            ->join('bundle_tickets', 'bundle_tickets.id', '=', 'bundle_ticket_rejects.bundle_ticket_id')
            ->join('bundles', 'bundles.id', '=', 'bundle_tickets.bundle_id')
            ->join('work_orders', 'work_orders.id', '=', 'bundles.work_order_id')
            ->where('bundle_ticket_rejects.active', true)
            ->where('bundle_tickets.active', true)
            ->where('bundles.active', true)
            ->where('work_orders.active', true)
            ->whereIn('work_orders.batch_detail_id', $batchDetailIds)
            ->groupBy('work_orders.batch_detail_id')
            ->selectRaw('work_orders.batch_detail_id, SUM(bundle_ticket_rejects.reject_qty) as total')
            ->pluck('total', 'batch_detail_id');

        foreach ($batchDetails as $batchDetail) {
            $totalBundleQty = (int) ($bundleQtyByBatchDetail[$batchDetail->id] ?? 0);
            $totalRejectQty = (int) ($rejectQtyByBatchDetail[$batchDetail->id] ?? 0);

            $batchDetail->total_bundle_qty = $totalBundleQty;
            $batchDetail->total_reject_qty = $totalRejectQty;
            $batchDetail->available_qty = $batchDetail->quantity + $totalRejectQty - $totalBundleQty;
        }
    }

    public function all(?string $status = null)
    {
        $query = WorkOrder::with(['batchDetail.batch', 'batchDetail.model.mainModel'])
            ->where('active', true);

        if ($status) {
            $query->where('status', strtoupper($status));
        }

        return $query->orderByDesc('id')->get();
    }

    public function find(int $id)
    {
        $workOrder = WorkOrder::with([
            'batchDetail.batch',
            'batchDetail.model.mainModel',
            'batchDetail.model.routeMaster',
            'workOrderOperations' => function ($query) {
                $query->where('active', true)->with('routingOperationMaster.operation');
            },
            'bundles' => function ($query) {
                $query->where('active', true)->with([
                    'trollyMaster',
                    'bundleDetails' => function ($detailQuery) {
                        $detailQuery->where('active', true)->with(['stockMaterial', 'whlItem.warehouseLocation']);
                    },
                ]);
            },
        ])->where('active', true)->find($id);

        if (!$workOrder) {
            return null;
        }

        if ($workOrder->batchDetail) {
            $this->assignAvailableQty(collect([$workOrder->batchDetail]));
        }

        $workOrder->setRelation(
            'workOrderOperations',
            $workOrder->workOrderOperations
                ->sortBy(fn ($woo) => optional($woo->routingOperationMaster)->seq)
                ->values()
        );

        return $workOrder;
    }

    /**
     * Create a work order from a batch detail and generate its routing
     * operations from the model's assigned route master.
     */
    public function create(int $batchDetailId): WorkOrder
    {
        return DB::transaction(function () use ($batchDetailId) {
            $batchDetail = BatchDetail::with('model')->findOrFail($batchDetailId);
            $model = $batchDetail->model;

            if (!$model || !$model->route_master_id) {
                throw new InvalidArgumentException("The selected batch detail's model has no routing assigned.");
            }

            $operations = RoutingOperationMaster::where('routing_id', $model->route_master_id)
                ->where('active', true)
                ->orderBy('seq')
                ->get();

            if ($operations->isEmpty()) {
                throw new InvalidArgumentException('The assigned routing has no active operations.');
            }

            $workOrder = WorkOrder::create([
                'batch_detail_id' => $batchDetailId,
                'status' => 'OPEN',
            ]);

            foreach ($operations as $operation) {
                WorkOrderOperation::create([
                    'work_order_id' => $workOrder->id,
                    'routing_operation_master_id' => $operation->id,
                    'smv' => $operation->smv,
                ]);
            }

            return $workOrder;
        });
    }

    /**
     * Add a bundle (a size/qty production lot) to an open work order,
     * optionally assigning it a trolley (which must currently be unused).
     */
    public function createBundle(int $workOrderId, ?string $size, int $qty, ?int $trollyMasterId): Bundle
    {
        return DB::transaction(function () use ($workOrderId, $size, $qty, $trollyMasterId) {
            $workOrder = WorkOrder::with('batchDetail')->where('active', true)->findOrFail($workOrderId);

            if ($workOrder->status !== 'OPEN') {
                throw new InvalidArgumentException('Bundles can only be added while the work order is open.');
            }

            if ($workOrder->batchDetail) {
                $this->assignAvailableQty(collect([$workOrder->batchDetail]));
                if ($qty > $workOrder->batchDetail->available_qty) {
                    throw new InvalidArgumentException("Only {$workOrder->batchDetail->available_qty} available on the selected batch detail.");
                }
            }

            $bundle = Bundle::create([
                'work_order_id' => $workOrderId,
                'trolly_master_id' => $trollyMasterId,
                'size' => $size,
                'qty' => $qty,
            ]);

            if ($trollyMasterId) {
                $this->assignTrolly($trollyMasterId, $bundle->id);
            }

            return $bundle;
        });
    }

    /**
     * Update a bundle's size/qty/trolley while its work order is still open.
     * Reassigning the trolley releases the old one (if any) and marks the
     * new one (if any) as used.
     */
    public function updateBundle(int $bundleId, ?string $size, int $qty, ?int $trollyMasterId): Bundle
    {
        return DB::transaction(function () use ($bundleId, $size, $qty, $trollyMasterId) {
            $bundle = Bundle::with('workOrder.batchDetail')->where('active', true)->lockForUpdate()->findOrFail($bundleId);

            if (!$bundle->workOrder || $bundle->workOrder->status !== 'OPEN') {
                throw new InvalidArgumentException('Bundles can only be edited while the work order is open.');
            }

            $batchDetail = $bundle->workOrder->batchDetail;
            if ($batchDetail) {
                $this->assignAvailableQty(collect([$batchDetail]));
                // This bundle's own current qty is already part of what
                // available_qty treats as "committed" - add it back so
                // editing (including increasing) up to what's truly
                // available isn't blocked by the bundle's own allocation.
                $maxQty = $batchDetail->available_qty + $bundle->qty;
                if ($qty > $maxQty) {
                    throw new InvalidArgumentException("Only {$maxQty} available on the selected batch detail.");
                }
            }

            $currentTrollyMasterId = $bundle->trolly_master_id !== null ? (int) $bundle->trolly_master_id : null;

            if ($currentTrollyMasterId !== $trollyMasterId) {
                if ($currentTrollyMasterId) {
                    $this->releaseTrolly($currentTrollyMasterId);
                }
                if ($trollyMasterId) {
                    $this->assignTrolly($trollyMasterId, $bundle->id);
                }
                $bundle->trolly_master_id = $trollyMasterId;
            }

            $bundle->size = $size;
            $bundle->qty = $qty;
            $bundle->save();

            return $bundle;
        });
    }

    /**
     * Mark a trolley as used and link it to the bundle it now carries -
     * throws if it's inactive or already in use.
     */
    private function assignTrolly(int $trollyMasterId, int $bundleId): void
    {
        $trolly = TrollyMaster::where('active', true)->lockForUpdate()->find($trollyMasterId);

        if (!$trolly) {
            throw new InvalidArgumentException('The selected trolley was not found.');
        }
        if ($trolly->used) {
            throw new InvalidArgumentException('The selected trolley is already in use.');
        }

        $trolly->used = true;
        $trolly->bundle_id = $bundleId;
        $trolly->save();
    }

    /**
     * Free up a trolley so it becomes available for other bundles again.
     */
    private function releaseTrolly(int $trollyMasterId): void
    {
        $trolly = TrollyMaster::where('id', $trollyMasterId)->lockForUpdate()->first();

        if ($trolly) {
            $trolly->used = false;
            $trolly->bundle_id = null;
            $trolly->save();
        }
    }

    /**
     * Pick material for a bundle from a scanned warehouse stock row,
     * reducing the available qty on that WhlItem row.
     */
    public function addBundleDetail(int $bundleId, int $whlItemId, int $qty): BundleDetail
    {
        return DB::transaction(function () use ($bundleId, $whlItemId, $qty) {
            $bundle = Bundle::with('workOrder')->where('active', true)->findOrFail($bundleId);

            if (!$bundle->workOrder || $bundle->workOrder->status !== 'OPEN') {
                throw new InvalidArgumentException('Materials can only be picked while the work order is open.');
            }

            $whlItem = WhlItem::where('id', $whlItemId)->lockForUpdate()->first();
            if (!$whlItem) {
                throw new InvalidArgumentException('The selected stock item was not found.');
            }

            if ($qty > $whlItem->qty) {
                throw new InvalidArgumentException("Only {$whlItem->qty} available to pick from this location.");
            }

            $whlItem->decrement('qty', $qty);

            return BundleDetail::create([
                'bundle_id' => $bundleId,
                'stock_material_id' => $whlItem->stock_item_id,
                'whl_item_id' => $whlItem->id,
                'qty' => $qty,
                'size' => $bundle->size,
            ]);
        });
    }

    /**
     * Delete a bundle detail pick, reversing the qty back onto its source
     * WhlItem row.
     */
    public function deleteBundleDetail(int $id): void
    {
        DB::transaction(function () use ($id) {
            $detail = BundleDetail::with('bundle.workOrder')->where('active', true)->findOrFail($id);

            if (!$detail->bundle || !$detail->bundle->workOrder || $detail->bundle->workOrder->status !== 'OPEN') {
                throw new InvalidArgumentException('Picks can only be removed while the work order is open.');
            }

            if ($detail->whl_item_id) {
                $whlItem = WhlItem::where('id', $detail->whl_item_id)->lockForUpdate()->first();
                if ($whlItem) {
                    $whlItem->increment('qty', $detail->qty);
                }
            }

            $detail->active = false;
            $detail->save();
        });
    }

    /**
     * Finalize a work order: lock it and pre-generate the bundle tickets
     * (one per bundle x work-order-operation x direction) that production
     * WIP scanning records progress against.
     */
    public function finalize(int $workOrderId): WorkOrder
    {
        return DB::transaction(function () use ($workOrderId) {
            $workOrder = WorkOrder::where('active', true)->lockForUpdate()->findOrFail($workOrderId);

            if ($workOrder->status !== 'OPEN') {
                throw new InvalidArgumentException('Only an open work order can be finalized.');
            }

            $bundles = Bundle::where('work_order_id', $workOrderId)->where('active', true)->get();
            if ($bundles->isEmpty()) {
                throw new InvalidArgumentException('Add at least one bundle before finalizing.');
            }

            $operations = WorkOrderOperation::with('routingOperationMaster')
                ->where('work_order_id', $workOrderId)
                ->where('active', true)
                ->get();

            foreach ($bundles as $bundle) {
                foreach ($operations as $operation) {
                    $rom = $operation->routingOperationMaster;
                    if (!$rom) {
                        continue;
                    }

                    $directions = array_keys(array_filter(['IN' => $rom->in, 'OUT' => $rom->out]));

                    foreach ($directions as $direction) {
                        // The unique (bundle_id, work_order_operation_id, direction)
                        // index means a ticket from a previous finalize/reopen
                        // cycle must be reactivated rather than re-created.
                        $ticket = BundleTicket::where('work_order_operation_id', $operation->id)
                            ->where('bundle_id', $bundle->id)
                            ->where('direction', $direction)
                            ->first();

                        if ($ticket) {
                            $ticket->qty = $bundle->qty;
                            $ticket->active = true;
                            $ticket->save();
                        } else {
                            BundleTicket::create([
                                'work_order_operation_id' => $operation->id,
                                'bundle_id' => $bundle->id,
                                'direction' => $direction,
                                'qty' => $bundle->qty,
                                'active' => true,
                            ]);
                        }
                    }
                }
            }

            $workOrder->status = 'FINALIZED';
            $workOrder->save();

            return $workOrder;
        });
    }

    /**
     * Reopen a finalized work order, deactivating its bundle tickets —
     * unless production scanning has already recorded qty against them.
     */
    public function reopen(int $workOrderId): WorkOrder
    {
        return DB::transaction(function () use ($workOrderId) {
            $workOrder = WorkOrder::where('active', true)->lockForUpdate()->findOrFail($workOrderId);

            if ($workOrder->status !== 'FINALIZED') {
                throw new InvalidArgumentException('Only a finalized work order can be reopened.');
            }

            $operationIds = WorkOrderOperation::where('work_order_id', $workOrderId)->pluck('id');

            $tickets = BundleTicket::whereIn('work_order_operation_id', $operationIds)
                ->where('active', true)
                ->get();

            $hasScans = BundleTicketSecondary::whereIn('bundle_ticket_id', $tickets->pluck('id'))
                ->where('active', true)
                ->where('scan_qty', '>', 0)
                ->exists();

            if ($hasScans) {
                throw new InvalidArgumentException('Cannot reopen: production scanning has already started on this work order.');
            }

            foreach ($tickets as $ticket) {
                $ticket->active = false;
                $ticket->save();
            }

            $workOrder->status = 'OPEN';
            $workOrder->save();

            return $workOrder;
        });
    }
}
