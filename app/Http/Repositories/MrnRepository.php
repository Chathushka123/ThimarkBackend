<?php

namespace App\Http\Repositories;

use App\Mrn;
use Exception;
use Illuminate\Support\Facades\Validator;

class MrnRepository
{
    public function getMrns()
    {
        return Mrn::with(['batch', 'warehouse'])->where('active', true)->orderBy('id', 'desc')->get();
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
}
