<?php

namespace App\Http\Controllers;

use App\Http\Repositories\WarehouseRepository;
use App\GrnDetail;
use App\Mrn;
use App\MrnDetail;
use App\WhlItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    protected $repository;

    public function __construct(WarehouseRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index()
    {
        return response()->json($this->repository->all());
    }

    /**
     * GET /api/v1/inventory/warehouse/{id}
     * Returns active racks → bins → items for the given warehouse.
     */
    public function getWarehouseStructure($id)
    {
        return response()->json($this->repository->getWarehouseStructure($id));
    }

    /**
     * POST /api/v1/inventory/transfer
     * Transfer a stock material between bins (same or different racks, full or partial qty).
     *
     * Body:
     *   whl_item_id  (int)   — source whl_item to transfer from
     *   to_whl_id    (int)   — destination warehouse_location (bin) id
     *   qty          (numeric) — quantity to transfer
     */
    public function transferStock(Request $request)
    {
        $validated = $request->validate([
            'whl_item_id' => 'required|integer|exists:whl_items,id',
            'to_whl_id'   => 'required|integer|exists:warehouse_locations,id',
            'qty'         => 'required|numeric|min:0.001',
        ]);

        try {
            $result = $this->repository->transferStock(
                (int) $validated['whl_item_id'],
                (int) $validated['to_whl_id'],
                $validated['qty']
            );
            return response()->json($result, 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/v1/inventory/balance
     * Returns the quantity for a specific location and stock item.
     *
     * Query params:
     *   location_id   (int) — warehouse location (whl_id)
     *   stock_item_id (int) — stock material id
     */
    public function getBalance(Request $request)
    {
        $validated = $request->validate([
            'location_id'   => 'required|integer|exists:warehouse_locations,id',
            'stock_item_id' => 'required|integer|exists:stock_materials,id',
        ]);

        $totalQty = \App\WhlItem::where('whl_id', $validated['location_id'])
            ->where('stock_item_id', $validated['stock_item_id'])
            ->where('active', true)
            ->sum('qty');

        return response()->json([
            'status' => 'success',
            'data' => [
                'location_id' => $validated['location_id'],
                'stock_item_id' => $validated['stock_item_id'],
                'qty' => $totalQty ?? 0
            ]
        ], 200);
    }

    /**
     * POST /api/v1/inventory/issue
     * Issue stock from warehouse location to MRN.
     * Updates mrn_details.issued_qty and decreases whl_items.qty using FIFO.
     *
     * Body:
     *   mrn_detail_id  (int)     — MRN detail record to update
     *   stock_item_id  (int)     — Stock material id
     *   location_id    (int)     — Warehouse location (whl_id)
     *   qty            (numeric) — Quantity to issue
     */
    public function issueStock(Request $request)
    {
        $validated = $request->validate([
            'mrn_detail_id' => 'required|integer|exists:mrn_details,id',
            'stock_item_id' => 'required|integer|exists:stock_materials,id',
            'location_id'   => 'required|integer|exists:warehouse_locations,id',
            'qty'           => 'required|numeric|min:0.001',
        ]);

        DB::beginTransaction();

        try {
            // 1. Update MRN Detail issued_qty
            $mrnDetail = MrnDetail::findOrFail($validated['mrn_detail_id']);
            $currentIssuedQty = $mrnDetail->issued_qty ?? 0;
            $mrnDetail->issued_qty = $currentIssuedQty + $validated['qty'];
            $mrnDetail->save();

            // 2. Update MRN status to processing
            $mrn = Mrn::findOrFail($mrnDetail->mrn_id);
            $mrn->status = 'processing';
            $mrn->save();

            // 3. Get available whl_items ordered by id (FIFO)
            $whlItems = WhlItem::where('whl_id', $validated['location_id'])
                ->where('stock_item_id', $validated['stock_item_id'])
                ->where('active', true)
                ->where('qty', '>', 0)
                ->orderBy('id', 'asc')
                ->get();

            // Check if sufficient qty available
            $totalAvailable = $whlItems->sum('qty');
            if ($totalAvailable < $validated['qty']) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient quantity available. Available: ' . $totalAvailable . ', Required: ' . $validated['qty']
                ], 422);
            }

            // 4. Deduct quantity using FIFO and calculate weighted average GRN price
            $remainingQty = $validated['qty'];
            $issuedItems = [];
            $totalWeightedPrice = 0;
            $totalQtyForAverage = 0;

            foreach ($whlItems as $whlItem) {
                if ($remainingQty <= 0) {
                    break;
                }

                $deductQty = min($remainingQty, $whlItem->qty);
                $whlItem->qty -= $deductQty;
                $whlItem->save();

                // Deduct from grn_details.available_qty using FIFO
                $grnDetails = GrnDetail::where('whl_item_id', $whlItem->id)
                    ->where('active', true)
                    ->where('available_qty', '>', 0)
                    ->orderBy('id', 'asc')
                    ->get();

                $remainingDeductQty = $deductQty;
                $grnDeductions = [];

                foreach ($grnDetails as $grnDetail) {
                    if ($remainingDeductQty <= 0) {
                        break;
                    }

                    $grnDeductAmount = min($remainingDeductQty, $grnDetail->available_qty);
                    $grnDetail->available_qty = max(0, $grnDetail->available_qty - $grnDeductAmount);
                    $grnDetail->save();

                    // Calculate weighted price for average
                    $totalWeightedPrice += ($grnDetail->grn_price * $grnDeductAmount);
                    $totalQtyForAverage += $grnDeductAmount;

                    $grnDeductions[] = [
                        'grn_detail_id' => $grnDetail->id,
                        'grn_price' => $grnDetail->grn_price,
                        'deducted_qty' => $grnDeductAmount,
                        'remaining_available_qty' => $grnDetail->available_qty
                    ];

                    $remainingDeductQty -= $grnDeductAmount;
                }

                $issuedItems[] = [
                    'whl_item_id' => $whlItem->id,
                    'deducted_qty' => $deductQty,
                    'remaining_qty' => $whlItem->qty,
                    'grn_deductions' => $grnDeductions
                ];

                $remainingQty -= $deductQty;
            }

            // 5. Update MRN Detail with weighted average GRN price
            if ($totalQtyForAverage > 0) {
                $averageGrnPrice = $totalWeightedPrice / $totalQtyForAverage;
                $mrnDetail->grn_price = $averageGrnPrice;
                $mrnDetail->save();
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Stock issued successfully',
                'data' => [
                    'mrn_id' => $mrn->id,
                    'mrn_status' => $mrn->status,
                    'mrn_detail_id' => $mrnDetail->id,
                    'issued_qty' => $mrnDetail->issued_qty,
                    'grn_price' => $mrnDetail->grn_price,
                    'average_grn_price' => $totalQtyForAverage > 0 ? ($totalWeightedPrice / $totalQtyForAverage) : 0,
                    'total_issued' => $validated['qty'],
                    'issued_from' => $issuedItems
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/mrn-issuance/delete/{mrn_detail_id}
     * Reverse/delete a stock issuance from MRN detail.
     * Resets mrn_details.issued_qty and grn_price to null.
     * Increases whl_items.qty and grn_details.available_qty.
     *
     * Params:
     *   mrn_detail_id (int) — MRN detail record ID to reverse
     */
    public function deleteIssuance($mrnDetailId)
    {
        DB::beginTransaction();

        try {
            // 1. Get MRN Detail
            $mrnDetail = MrnDetail::findOrFail($mrnDetailId);

            if (!$mrnDetail->issued_qty || $mrnDetail->issued_qty <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No issued quantity found for this MRN detail'
                ], 422);
            }

            $issuedQty = $mrnDetail->issued_qty;
            $stockItemId = $mrnDetail->stock_item_id;

            // 2. Find the last (highest id) whl_item for this stock_item_id
            $whlItem = WhlItem::where('stock_item_id', $stockItemId)
                ->where('active', true)
                ->orderBy('id', 'desc')
                ->first();

            if (!$whlItem) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'No warehouse location item found for this stock item'
                ], 422);
            }

            // 3. Increase whl_item qty
            $whlItem->qty += $issuedQty;
            $whlItem->save();

            // 4. Increase grn_details.available_qty for this whl_item
            $grnDetails = GrnDetail::where('whl_item_id', $whlItem->id)
                ->where('active', true)
                ->orderBy('id', 'desc')
                ->get();

            $remainingQtyToRestore = $issuedQty;
            $restoredGrnDetails = [];

            foreach ($grnDetails as $grnDetail) {
                $available = $grnDetail->available_qty > 0 ? $grnDetail->available_qty : 0;
                $available += $remainingQtyToRestore;
                $grnDetail->available_qty = $available;
                $grnDetail->save();

                // if ($remainingQtyToRestore <= 0) {
                //     break;
                // }

                // // Calculate how much we can restore (up to original qty)
                // $maxRestorableQty = $grnDetail->qty - $grnDetail->available_qty;
                // $restoreAmount = min($remainingQtyToRestore, $maxRestorableQty);

                // if ($restoreAmount > 0) {
                //     $grnDetail->available_qty += $restoreAmount;
                //     $grnDetail->save();

                //     $restoredGrnDetails[] = [
                //         'grn_detail_id' => $grnDetail->id,
                //         'restored_qty' => $restoreAmount,
                //         'new_available_qty' => $grnDetail->available_qty
                //     ];

                //     $remainingQtyToRestore -= $restoreAmount;
                // }
            }

            // 5. Reset MRN Detail issued_qty and grn_price to null
            $mrnDetail->issued_qty = null;
            $mrnDetail->grn_price = null;
            $mrnDetail->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Stock issuance deleted successfully',
                'data' => [
                    'mrn_detail_id' => $mrnDetail->id,
                    'restored_qty' => $issuedQty,
                    'whl_item_id' => $whlItem->id,
                    'whl_item_new_qty' => $whlItem->qty,
                    'restored_grn_details' => $restoredGrnDetails
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/v1/mrn-issuance/complete
     * Complete an MRN by updating its status to 'complete'.
     *
     * Body:
     *   mrn_id (int) — MRN ID to complete
     */
    public function completeIssuance(Request $request)
    {
        $validated = $request->validate([
            'mrn_id' => 'required|integer|exists:mrns,id',
        ]);

        DB::beginTransaction();

        try {
            $mrn = Mrn::where('active', true)->findOrFail($validated['mrn_id']);

            $mrn->status = 'complete';
            $mrn->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'MRN completed successfully',
                'data' => [
                    'mrn_id' => $mrn->id,
                    'status' => $mrn->status,
                    'batch_id' => $mrn->batch_id,
                    'warehouse_id' => $mrn->warehouse_id
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Retiurnable save
    public function saveReturnable(Request $request)
    {
        try {
            $validated = $request->validate([
                'issued_to' => 'required|integer|exists:users,id',
                'total_qty' => 'required|numeric|min:0.001',
                'issued_qty' => 'required|numeric|min:0',
                'return_qty' => 'required|numeric|min:0',
                'stock_item_id' => 'required|integer|exists:stock_materials,id',
            ]);

            $returnable = \App\Returnable::create($validated);
            return response()->json([
                'status' => 'success',
                'message' => 'Returnable record created successfully',
                'data' => $returnable
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getReturnable(Request $request)
    {
        $validated = $request->validate([
            'issued_to' => 'required|integer|exists:users,id',
        ]);

        $returnables = \App\Returnable::where('issued_to', $validated['issued_to'])
            ->join('stock_materials', 'returnables.stock_item_id', '=', 'stock_materials.id')
            ->select(
                'returnables.*',
                'stock_materials.name as material_name',
                'stock_materials.code as material_code'
            )
            ->where('returnables.active', true)
            ->where('stock_materials.active', true)
            ->whereColumn('returnables.issued_qty', '>', 'returnables.return_qty')
            ->with('stockItem')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $returnables
        ], 200);
    }

    public function updateReturnable(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer|exists:returnables,id',
                'return_qty' => 'required|numeric|min:0',
            ]);

            $returnable = \App\Returnable::findOrFail($validated['id']);

            if ($validated['return_qty'] > $returnable->issued_qty) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Return quantity cannot exceed issued quantity'
                ], 422);
            }

            $returnable->return_qty = $validated['return_qty'];
            $returnable->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Returnable record updated successfully',
                'data' => $returnable
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
