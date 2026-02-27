<?php

namespace App\Http\Repositories;

use App\Uom;
use Exception;
use Illuminate\Support\Facades\Validator;

class UomRepository
{
    public function getUoms()
    {
        return Uom::where('active', true)->orderBy('id', 'desc')->get();
    }

    public function createAndUpdateUom($request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|min:1',
            'uom' => 'required|string|max:100',
            'active' => 'nullable|boolean',
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
                'uom' => $request->input('uom'),
                'active' => $request->has('active') ? (bool) $request->input('active') : true,
            ];

            if ($id) {
                $model = Uom::findOrFail($id);
                $model->update($payload);
            } else {
                $model = Uom::create($payload);
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

    public function deleteUom($request)
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
            $model = Uom::findOrFail($request->input('id'));
            $model->active = false;
            $model->save();

            return response()->json([
                'status' => 'success',
                'message' => 'UOM deleted successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
