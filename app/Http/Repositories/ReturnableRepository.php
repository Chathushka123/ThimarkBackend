<?php

namespace App\Http\Repositories;

use App\Returnable;
use Exception;
use Illuminate\Support\Facades\Validator;

class ReturnableRepository
{
    public function getReturnables()
    {
        return Returnable::with('issuedTo')->where('active', true)->orderBy('id', 'desc')->get();
    }

    public function createAndUpdateReturnable($request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|min:1',
            'issued_to' => 'required|integer|exists:users,id',
            'total_qty' => 'required|numeric',
            'issued_qty' => 'required|numeric',
            'return_qty' => 'required|numeric',
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
                'issued_to' => $request->input('issued_to'),
                'total_qty' => $request->input('total_qty'),
                'issued_qty' => $request->input('issued_qty'),
                'return_qty' => $request->input('return_qty'),
            ];

            if ($id) {
                $model = Returnable::where('active', true)->findOrFail($id);
                $model->update($payload);
            } else {
                $model = Returnable::create($payload);
            }

            return response()->json([
                'status' => 'success',
                'data' => $model->load('issuedTo'),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteReturnable($request)
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
            $model = Returnable::where('active', true)->findOrFail($request->input('id'));
            $model->active = false;
            $model->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Returnable deleted successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
