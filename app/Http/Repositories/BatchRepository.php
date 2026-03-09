<?php

namespace App\Http\Repositories;

use App\Batch;
use Exception;
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
            ->where('batches.active', true)
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
                ->where('active', true)
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
