<?php

namespace App\Http\Repositories;

use App\Batch;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BatchRepository
{
    public function getBatches()
    {
        return Batch::with('model')->where('active', true)->orderBy('id', 'desc')->get();
    }

    public function createAndUpdateBatch($request)
    {
        $id = $request->input('id');

        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|min:1',
            'model_id' => 'required|integer|exists:models,id',
            'batch_no' => ['required', 'string', 'max:255', Rule::unique('batches', 'batch_no')->ignore($id)],
            'qty_json' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 400);
        }

        $qtyJson = $request->input('qty_json');
        if (is_string($qtyJson)) {
            json_decode($qtyJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'status' => 'error',
                    'message' => ['qty_json' => ['The qty_json must be a valid JSON string or array.']],
                ], 400);
            }
        } elseif (!is_array($qtyJson)) {
            return response()->json([
                'status' => 'error',
                'message' => ['qty_json' => ['The qty_json must be a valid JSON string or array.']],
            ], 400);
        }

        try {
            $payload = [
                'model_id' => $request->input('model_id'),
                'batch_no' => $request->input('batch_no'),
                'qty_json' => $qtyJson,
            ];

            if ($id) {
                $model = Batch::findOrFail($id);
                $model->update($payload);
            } else {
                $model = Batch::create($payload);
            }

            return response()->json([
                'status' => 'success',
                'data' => $model->load('model'),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getSearchByBatch($request)
    {
        $id         = $request->input('id');
        $batch_no   = $request->input('batch_no');
        $model_name = $request->input('model_name');

        $results = Batch::select(
            'batches.id',
            'batches.batch_no',
            'models.name as model_name',
            'batches.qty_json',
            'batches.model_id',
        )
            ->join('models', 'batches.model_id', '=', 'models.id')
            ->where('batches.id',       'LIKE', ($id         === '%' ? '%' : '%' . $id . '%'))
            ->where('batches.batch_no', 'LIKE', ($batch_no   === '%' ? '%' : '%' . $batch_no . '%'))
            ->where('models.name',      'LIKE', ($model_name === '%' ? '%' : '%' . $model_name . '%'))
            ->orderBy('batches.id', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $results,
        ], 200);
    }

    public function getBatchById($request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors(),
            ], 400);
        }

        try {
            $batch = Batch::with('model')
                ->where('id', $request->input('id'))
                ->where('active', 1)
                ->firstOrFail();

            return response()->json([
                'status' => 'success',
                'data'   => $batch,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Batch not found.',
            ], 404);
        }
    }

    public function getCostSheetDataById($request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors(),
            ], 400);
        }

        try {
            $batch = Batch::with([
                'model.modelStockItems:id,model_id,stock_item_id,consumption',
                'model.modelStockItems.stockItem:id,name,code,unit_price,uom_id',
                'model.mainModel:id,name',
                'mrns.warehouse:id,name',
                'mrns.issuedTo:id,name,email',
            ])
                ->where('id', $request->input('id'))
                ->where('active', true)
                ->firstOrFail();

            $mrnSummary = DB::table('mrn_details')
                ->join('mrns', 'mrns.id', '=', 'mrn_details.mrn_id')
                ->join('stock_materials', 'stock_materials.id', '=', 'mrn_details.stock_item_id')
                // ->leftJoin('model_stock_items', 'model_stock_items.stock_item_id', '=', 'mrn_details.stock_item_id')
                ->where('mrns.batch_id', $batch->id)
                ->where('mrns.active', true)
                ->where('mrn_details.active', true)
                ->select(
                    'mrn_details.stock_item_id',
                    'stock_materials.name as stock_item_name',
                    'stock_materials.code as stock_item_code',
                    'stock_materials.category',
                    // 'model_stock_items.consumption as req_consumption',
                    DB::raw('SUM(mrn_details.qty) as total_qty'),
                    DB::raw('SUM(mrn_details.issued_qty) as total_issued_qty'),
                    DB::raw('AVG(mrn_details.grn_price) as avg_grn_price'),
                )
                ->groupBy('mrn_details.stock_item_id', 'stock_materials.name', 'stock_materials.code')
                ->get();

            return response()->json([
                'status'      => 'success',
                'data'        => $batch,
                'mrn_summary' => $mrnSummary,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Batch not found.',
            ], 404);
        }
    }

    public function getBatchComparisonByModel($request)
    {
        $validator = Validator::make($request->all(), [
            'model_id' => 'required|integer|min:1|exists:models,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors(),
            ], 400);
        }

        try {
            $modelId = $request->input('model_id');

            $model = \App\Model::with([
                'mainModel:id,name',
                'modelStockItems:id,model_id,stock_item_id,consumption',
                'modelStockItems.stockItem:id,name,code,unit_price,uom_id',
            ])->findOrFail($modelId);

            // Batches desc for list, asc for time-series charts
            $batches = \App\Batch::where('model_id', $modelId)
                ->where('active', true)
                ->orderBy('id', 'asc')
                ->get(['id', 'batch_no', 'qty_json']);

            $batchIds = $batches->pluck('id');

            $rows = DB::table('mrn_details')
                ->join('mrns', 'mrns.id', '=', 'mrn_details.mrn_id')
                ->join('stock_materials', 'stock_materials.id', '=', 'mrn_details.stock_item_id')
                ->whereIn('mrns.batch_id', $batchIds)
                ->where('mrns.active', true)
                ->where('mrn_details.active', true)
                ->select(
                    'mrns.batch_id',
                    'mrn_details.stock_item_id',
                    'stock_materials.name as stock_item_name',
                    'stock_materials.code as stock_item_code',
                    'stock_materials.category',
                    DB::raw('SUM(mrn_details.qty) as total_qty'),
                    DB::raw('SUM(mrn_details.issued_qty) as total_issued_qty'),
                    DB::raw('AVG(mrn_details.grn_price) as avg_grn_price'),
                )
                ->groupBy('mrns.batch_id', 'mrn_details.stock_item_id', 'stock_materials.name', 'stock_materials.code', 'stock_materials.category')
                ->get();

            $stockItems = $rows->unique('stock_item_id')->map(fn($r) => [
                'stock_item_id' => $r->stock_item_id,
                'name'          => $r->stock_item_name,
                'code'          => $r->stock_item_code,
                'category'      => $r->category,
            ])->values();

            // Pivot: [stock_item_id][batch_id]
            $comparison = [];
            foreach ($rows as $row) {
                $comparison[$row->stock_item_id][$row->batch_id] = [
                    'total_qty'        => (float) $row->total_qty,
                    'total_issued_qty' => (float) $row->total_issued_qty,
                    'avg_grn_price'    => round((float) $row->avg_grn_price, 4),
                ];
            }

            // Required consumption per stock_item_id from model definition
            $reqConsumptionMap = $model->modelStockItems->keyBy('stock_item_id')
                ->map(fn($msi) => (float) $msi->consumption);

            // Total batch qty: sum of all size values in qty_json
            $batchTotalQtyMap = $batches->mapWithKeys(function ($batch) {
                $qty   = is_array($batch->qty_json) ? $batch->qty_json : json_decode($batch->qty_json, true);
                $total = array_sum(array_values($qty ?? []));
                return [$batch->id => max($total, 1)];
            });

            $batchLabels  = $batches->pluck('batch_no')->values();
            $batchIdsAsc  = $batches->pluck('id')->values();

            // Chart 1: Grouped bar — req vs actual consumption per stock item, one dataset per batch
            $reqVsActual = [
                'type'   => 'grouped_bar',
                'labels' => $stockItems->pluck('name')->values(),
                'batches' => $batches->map(function ($batch) use ($stockItems, $comparison, $reqConsumptionMap, $batchTotalQtyMap) {
                    $batchTotal = $batchTotalQtyMap[$batch->id];
                    $required   = [];
                    $actual     = [];
                    foreach ($stockItems as $item) {
                        $sid        = $item['stock_item_id'];
                        $required[] = $reqConsumptionMap[$sid] ?? null;
                        $row        = $comparison[$sid][$batch->id] ?? null;
                        $actual[]   = $row ? round($row['total_issued_qty'] / $batchTotal, 4) : null;
                    }
                    return [
                        'batch_id' => $batch->id,
                        'batch_no' => $batch->batch_no,
                        'required' => $required,
                        'actual'   => $actual,
                    ];
                })->values(),
            ];

            // Chart 2: Multi-line — actual consumption per unit over batches, one line per material
            $consumptionTrend = [
                'type'   => 'multi_line',
                'labels' => $batchLabels,
                'series' => $stockItems->map(function ($item) use ($batchIdsAsc, $comparison, $batchTotalQtyMap) {
                    $sid = $item['stock_item_id'];
                    return [
                        'stock_item_id' => $sid,
                        'name'          => $item['name'],
                        'code'          => $item['code'],
                        'data'          => $batchIdsAsc->map(function ($batchId) use ($sid, $comparison, $batchTotalQtyMap) {
                            $row = $comparison[$sid][$batchId] ?? null;
                            return $row ? round($row['total_issued_qty'] / $batchTotalQtyMap[$batchId], 4) : null;
                        })->values(),
                    ];
                })->values(),
            ];

            // Chart 3: Multi-line — total material cost (avg_grn_price × issued_qty) over batches
            $costTrend = [
                'type'   => 'multi_line',
                'labels' => $batchLabels,
                'series' => $stockItems->map(function ($item) use ($batchIdsAsc, $comparison) {
                    $sid = $item['stock_item_id'];
                    return [
                        'stock_item_id' => $sid,
                        'name'          => $item['name'],
                        'code'          => $item['code'],
                        'data'          => $batchIdsAsc->map(function ($batchId) use ($sid, $comparison) {
                            $row = $comparison[$sid][$batchId] ?? null;
                            return $row ? round($row['avg_grn_price'] * $row['total_issued_qty'], 2) : null;
                        })->values(),
                    ];
                })->values(),
            ];

            return response()->json([
                'status'      => 'success',
                'model'       => $model,
                'batches'     => $batches,
                'stock_items' => $stockItems,
                'comparison'  => $comparison,
                'chart_data'  => [
                    'req_vs_actual'     => $reqVsActual,
                    'consumption_trend' => $consumptionTrend,
                    'cost_trend'        => $costTrend,
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteBatch($request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 400);
        }

        try {
            $model = Batch::findOrFail($request->input('id'));
            $model->active = false;
            $model->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Batch deleted successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
