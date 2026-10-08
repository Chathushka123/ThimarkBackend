<?php

namespace App\Http\Repositories;

use App\TrollyMaster;
use Exception;
use Illuminate\Support\Facades\Validator;

class TrollyMasterRepository
{
    public function getAll()
    {
        return TrollyMaster::where('active', true)->orderBy('id', 'desc')->get();
    }

    public function getOne($id)
    {
        try {
            $trollyMaster = TrollyMaster::find($id);

            if (!$trollyMaster) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Trolly not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $trollyMaster,
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
            'id' => 'nullable|integer|min:1',
            'code' => 'required|string|max:100',
            'name' => 'required|string|max:255',
            'active' => 'nullable|boolean',
            'bundle_id' => 'nullable|integer|min:1',
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
                'code' => $request->input('code'),
                'name' => $request->input('name'),
                'active' => $request->has('active') ? (bool) $request->input('active') : true,
            ];

            if ($id) {
                // An explicit bundle_id: null releases the trolley from its bundle
                // (same as WorkOrderRepository::releaseTrolly) so it can be reused.
                if ($request->exists('bundle_id') && $request->input('bundle_id') === null) {
                    $payload['bundle_id'] = null;
                    $payload['used'] = false;
                }

                $trollyMaster = TrollyMaster::findOrFail($id);
                $trollyMaster->update($payload);
            } else {
                $trollyMaster = TrollyMaster::create($payload);
            }

            return response()->json([
                'status' => 'success',
                'data' => $trollyMaster,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete($request)
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
            $trollyMaster = TrollyMaster::findOrFail($request->input('id'));
            $trollyMaster->active = false;
            $trollyMaster->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Trolly deleted successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
