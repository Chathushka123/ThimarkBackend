<?php

namespace App\Http\Controllers;

use App\Grn;
use App\GrnDetail;
use App\WhlItem;
use App\WarehouseLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class GrnController extends Controller
{
    public function index()
    {
        return Grn::with(['creator', 'warehouse'])->get();
    }

    public function show($id)
    {
        return Grn::with(['creator', 'warehouse'])->findOrFail($id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'status'       => 'nullable|string|max:255',
            'rmpono'       => 'nullable|string|max:255',
            'remark'       => 'nullable|string',
        ]);

        $grn = Grn::create([
            'warehouse_id' => $request->input('warehouse_id'),
            'status'       => $request->input('status'),
            'rmpono'       => $request->input('rmpono'),
            'remark'       => $request->input('remark'),
        ]);

        return $grn->load(['creator', 'warehouse']);
    }

    public function destroy($id)
    {
        $grn = Grn::findOrFail($id);
        $grn->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function getTransactions($id)
    {
        $grn = Grn::findOrFail($id);

        $transactions = GrnDetail::with([
            'whlItem.stockItem',
            'whlItem.warehouseLocation',
        ])
            ->where('grn_id', $id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($detail) {
                return [
                    'id'             => $detail->id,
                    'grn_id'         => $detail->grn_id,
                    'whl_item_id'    => $detail->whl_item_id,
                    'qty'            => $detail->qty,
                    'available_qty'  => $detail->available_qty,
                    'grn_price'      => $detail->grn_price,
                    'stock_item_id'  => optional($detail->whlItem)->stock_item_id,
                    'stock_item_name' => optional(optional($detail->whlItem)->stockItem)->name,
                    'location_id'    => optional($detail->whlItem)->whl_id,
                    'rack'           => optional(optional($detail->whlItem)->warehouseLocation)->rack,
                    'location'       => optional(optional($detail->whlItem)->warehouseLocation)->bin,
                    'created_at'     => $detail->created_at,
                ];
            });

        return response()->json([
            'status' => 'success',
            'grn_id' => (int) $id,
            'data'   => $transactions,
        ], 200);
    }

    // public function updateStatus(Request $request)
    // {
    //     $request->validate([
    //         'id'     => 'required|integer|exists:grns,id',
    //         'status' => 'required|string|max:255',
    //     ]);

    //     try {
    //         $grn = Grn::findOrFail($request->input('id'));
    //         $grn->status = $request->input('status');
    //         $grn->save();

    //         return response()->json([
    //             'status'  => 'success',
    //             'message' => 'GRN status updated successfully',
    //             'data'    => [
    //                 'id'     => $grn->id,
    //                 'status' => $grn->status,
    //             ],
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status'  => 'error',
    //             'message' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function search(Request $request)
    {
        $query = Grn::with(['creator', 'warehouse']);

        if ($request->input('grn_id_search') !== '') {
            $query->where('id', 'LIKE', '%' . $request->input('grn_id_search') . '%');
        }

        if ($request->input('rmpo_no_search') !== '') {
            $query->where('rmpono', 'LIKE', '%' . $request->input('rmpo_no_search') . '%');
        }

        if ($request->input('status_search') !== '') {
            $query->where('status', 'LIKE', '%' . $request->input('status_search') . '%');
        }

        $results = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data'   => $results,
        ], 200);
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'id'     => 'required|integer|exists:grns,id',
            'status' => 'required|string|max:255',
        ]);

        try {
            $grn = Grn::findOrFail($request->input('id'));
            $grn->status = $request->input('status');
            $grn->save();

            return response()->json([
                'status'  => 'success',
                'message' => 'GRN status updated successfully',
                'data'    => [
                    'id'     => $grn->id,
                    'status' => $grn->status,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteTransaction(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:grn_details,id',
        ]);

        try {
            DB::beginTransaction();

            $detail = GrnDetail::findOrFail($request->input('id'));

            // Reduce the qty from the WhlItem
            $whlItem = WhlItem::findOrFail($detail->whl_item_id);
            $whlItem->decrement('qty', $detail->qty);

            // Soft-delete the GrnDetail (sets active = false)
            $detail->delete();

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Transaction deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function addTransaction(Request $request)
    {
        $request->validate([
            'grn_id'        => 'required|integer|exists:grns,id',
            'location_id'   => 'required|integer|exists:warehouse_locations,id',
            'stock_item_id' => 'nullable|integer|exists:stock_materials,id',
            'quantity'      => 'required|numeric|min:-1000',
            'grn_price'     => 'required|numeric|min:0'
        ]);

        try {
            DB::beginTransaction();

            $stockItemId = $request->input('stock_item_id');

            // Resolve stock_item_id when not provided by the caller
            if (!$stockItemId) {
                $location = WarehouseLocation::with('warehouse')
                    ->findOrFail($request->input('location_id'));

                // Warehouse NOT location_basis → stock_item_id is mandatory
                if (!$location->warehouse->location_basis) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'stock_item_id is required for this warehouse.',
                    ], 422);
                }

                // location_basis = true: check distinct active stock items in this location
                $activeStockItemIds = WhlItem::where('whl_id', $location->id)
                    ->where('qty', '>', 0)
                    ->pluck('stock_item_id')
                    ->unique();

                if ($activeStockItemIds->count() > 1) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Selected location has multiple items. Please provide stock_item_id.',
                    ], 422);
                }

                // Fall back to the stock_item_id assigned on the location record
                $stockItemId = $location->stock_item_id;

                if (!$stockItemId) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'No stock item is assigned to this location. Please provide stock_item_id.',
                    ], 422);
                }
            }

            // Find or create a WhlItem for this location + stock_item
            $whlItem = WhlItem::firstOrCreate(
                [
                    'whl_id'        => $request->input('location_id'),
                    'stock_item_id' => $stockItemId,
                ],
                ['qty' => 0]
            );

            // Increment the stock quantity on the WhlItem
            $whlItem->increment('qty', $request->input('quantity'));

            // Create the GRN detail record
            GrnDetail::create([
                'grn_id'        => $request->input('grn_id'),
                'whl_item_id'   => $whlItem->id,
                'qty'           => $request->input('quantity'),
                'available_qty' => $request->input('quantity'),
                'grn_price'     => $request->input('grn_price'),
            ]);

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Transaction added successfully',
                'data'    => [
                    'grn_id'        => $request->input('grn_id'),
                    'whl_item_id'   => $whlItem->id,
                    'location_id'   => $request->input('location_id'),
                    'stock_item_id' => $stockItemId,
                    'quantity'      => $request->input('quantity'),
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
