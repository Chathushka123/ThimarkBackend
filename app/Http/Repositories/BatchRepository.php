<?php

namespace App\Http\Repositories;

use App\Batch;
use Exception;
use Illuminate\Support\Facades\Validator;

class BatchRepository
{
    public function getBatches()
    {
        return Batch::where('active', true)->orderBy('id', 'desc')->get();
    }

    public function createAndUpdateBatch($request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|min:1',
            'batch_no' => 'required|string|max:255',
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
            $id = $request->input('id');
            $payload = [
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
                'data' => $model,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
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
