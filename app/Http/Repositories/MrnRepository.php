<?php

namespace App\Http\Repositories;

use App\Mrn;
use App\MrnDetail;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MrnRepository
{
    public function getMrns()
    {
        return Mrn::with(['batch', 'warehouse'])->where('active', true)->orderBy('id', 'desc')->get();
    }

    public function getMrnById($id)
    {
        try {
            $mrn = Mrn::with([
                'batch',
                'warehouse',
                'details' => function ($query) {
                    $query->where('active', true);
                },
                'details.stockItem'
            ])
                ->where('active', true)
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $mrn,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'MRN not found',
            ], 404);
        }
    }

    public function createAndUpdateMrn($request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|min:1',
            'batch_id' => 'required|integer|exists:batches,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 400);
        }

        try {
            $id = $request->input('id');
            $payload = [
                'batch_id' => $request->input('batch_id'),
                'warehouse_id' => $request->input('warehouse_id'),
            ];

            if ($id) {
                $model = Mrn::where('active', true)->findOrFail($id);
                $model->update($payload);
            } else {
                $model = Mrn::create($payload);
            }

            return response()->json([
                'status' => 'success',
                'data' => $model->load(['batch', 'warehouse']),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteMrn($request)
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
            $model = Mrn::where('active', true)->findOrFail($request->input('id'));
            $model->active = false;
            $model->save();

            return response()->json([
                'status' => 'success',
                'message' => 'MRN deleted successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function createAndUpdate($request)
    {
        $validator = Validator::make($request->all(), [
            'mrn_id' => 'nullable|integer|min:1',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'batch_id' => 'required|integer|exists:batches,id',
            'status' => 'required|string|in:open,finalized,processing,complete',
            'mrn_details' => 'required|array|min:1',
            'mrn_details.*.stock_item_id' => 'required|integer|exists:stock_materials,id',
            'mrn_details.*.qty' => 'required|numeric|min:0',
            'mrn_details.*.id' => 'nullable|integer|min:1',
            'mrn_details.*.status' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 400);
        }

        DB::beginTransaction();

        try {
            $mrnId = $request->input('mrn_id');
            $mrnPayload = [
                'warehouse_id' => $request->input('warehouse_id'),
                'status' => $request->input('status'),
                'batch_id' => $request->input('batch_id'),
            ];

            // Create or Update MRN
            if ($mrnId) {
                $mrn = Mrn::where('active', true)->findOrFail($mrnId);
                $mrn->update($mrnPayload);
            } else {
                $mrn = Mrn::create($mrnPayload);
            }

            // Process MRN Details
            $mrnDetails = $request->input('mrn_details', []);
            $processedDetails = [];

            foreach ($mrnDetails as $detail) {
                $detailId = $detail['id'] ?? null;
                $detailStatus = $detail['status'] ?? '';

                $detailPayload = [
                    'mrn_id' => $mrn->id,
                    'stock_item_id' => $detail['stock_item_id'],
                    'qty' => $detail['qty'],
                ];

                if ($detailId) {
                    // Update existing detail
                    $mrnDetail = MrnDetail::withoutGlobalScope('active')->findOrFail($detailId);

                    if (strtoupper($detailStatus) === 'DELETED') {
                        // Soft delete by setting active to false
                        $mrnDetail->active = false;
                        $mrnDetail->save();
                    } else {
                        // Update detail
                        $mrnDetail->update($detailPayload);
                    }

                    $processedDetails[] = $mrnDetail;
                } else {
                    // Create new detail
                    if (strtoupper($detailStatus) !== 'DELETED') {
                        $mrnDetail = MrnDetail::create($detailPayload);
                        $processedDetails[] = $mrnDetail;
                    }
                }
            }

            DB::commit();

            // Load relationships for response
            $mrn->load(['warehouse', 'details.stockItem']);

            return response()->json([
                'status' => 'success',
                'message' => $mrnId ? 'MRN updated successfully' : 'MRN created successfully',
                'data' => $mrn,
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function finalizeMrn($request)
    {
        $validator = Validator::make($request->all(), [
            'mrn_id' => 'required|integer|min:1|exists:mrns,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 400);
        }

        try {
            $mrn = Mrn::where('active', true)->findOrFail($request->input('mrn_id'));
            $mrn->status = 'finalized';
            $mrn->save();

            return response()->json([
                'status' => 'success',
                'message' => 'MRN finalized successfully',
                'data' => $mrn->load(['batch', 'warehouse', 'details.stockItem']),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function reopenMrn($request)
    {
        $validator = Validator::make($request->all(), [
            'mrn_id' => 'required|integer|min:1|exists:mrns,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 400);
        }

        try {
            $mrn = Mrn::where('active', true)->findOrFail($request->input('mrn_id'));
            $mrn->status = 'open';
            $mrn->save();

            return response()->json([
                'status' => 'success',
                'message' => 'MRN reopened successfully',
                'data' => $mrn->load(['batch', 'warehouse', 'details.stockItem']),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getSearchByMrn($request)
    {
        $id         = $request->input('id');
        $batch_no   = $request->input('batch_no');
        $model_name = $request->input('model_name');
        $warehouse_name = $request->input('warehouse_name');
        $status = $request->input('status');

        $results = Mrn::select(
            'mrns.id',
            'batches.batch_no',
            'models.name as model_name',
            'mrns.status',
            'warehouses.name as warehouse_name'
        )
            ->join('batches', 'mrns.batch_id', '=', 'batches.id')
            ->join('models', 'batches.model_id', '=', 'models.id')
            ->join('warehouses', 'mrns.warehouse_id', '=', 'warehouses.id')
            ->where('mrns.active', true)
            ->where('mrns.id',       'LIKE', ($id         === '%' ? '%' : '%' . $id . '%'))
            ->where('batches.batch_no', 'LIKE', ($batch_no   === '%' ? '%' : '%' . $batch_no . '%'))
            ->where('models.name',      'LIKE', ($model_name === '%' ? '%' : '%' . $model_name . '%'))
            ->where('warehouses.name',  'LIKE', ($warehouse_name === '%' ? '%' : '%' . $warehouse_name . '%'))
            ->where('mrns.status',      'LIKE', ($status === '%' ? '%' : '%' . $status . '%'))
            ->orderBy('mrns.id', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $results,
        ], 200);
    }
}
