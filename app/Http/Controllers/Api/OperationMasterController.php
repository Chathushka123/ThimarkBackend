<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOperationMasterRequest;
use App\Http\Requests\UpdateOperationMasterRequest;
use App\OperationMaster;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Handles CRUD operations for the OperationMaster (operation) master table.
 */
class OperationMasterController extends Controller
{
    /**
     * Create a new operation master record.
     *
     * @param StoreOperationMasterRequest $request
     * @return JsonResponse
     */
    public function createRec(StoreOperationMasterRequest $request): JsonResponse
    {
        try {
            $operationMaster = OperationMaster::create([
                'operation_code' => $request->input('operation_code'),
                'description' => $request->input('description'),
                'active' => $request->boolean('active', true),
                'is_final_operation' => $request->boolean('is_final_operation', false),
            ]);

            return $this->success('Operation created successfully.', $operationMaster, 201);
        } catch (Exception $e) {
            return $this->error('Failed to create operation.', $e->getMessage(), 500);
        }
    }

    /**
     * Update an existing operation master record.
     *
     * @param UpdateOperationMasterRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateRec(UpdateOperationMasterRequest $request, int $id): JsonResponse
    {
        try {
            $operationMaster = OperationMaster::find($id);

            if (!$operationMaster) {
                return $this->error('Operation not found.', null, 404);
            }

            $operationMaster->update([
                'operation_code' => $request->input('operation_code'),
                'description' => $request->input('description'),
                'active' => $request->boolean('active', $operationMaster->active),
                'is_final_operation' => $request->boolean('is_final_operation', $operationMaster->is_final_operation),
            ]);

            return $this->success('Operation updated successfully.', $operationMaster);
        } catch (Exception $e) {
            return $this->error('Failed to update operation.', $e->getMessage(), 500);
        }
    }

    /**
     * Soft delete an operation master record by marking it inactive.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function deleteRec(int $id): JsonResponse
    {
        try {
            $operationMaster = OperationMaster::find($id);

            if (!$operationMaster) {
                return $this->error('Operation not found.', null, 404);
            }

            $operationMaster->active = false;
            $operationMaster->save();

            return $this->success('Operation deactivated successfully.', $operationMaster);
        } catch (Exception $e) {
            return $this->error('Failed to deactivate operation.', $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve a single operation master record.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getOne(int $id): JsonResponse
    {
        try {
            $operationMaster = OperationMaster::find($id);

            if (!$operationMaster) {
                return $this->error('Operation not found.', null, 404);
            }

            return $this->success('Operation retrieved successfully.', $operationMaster);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve operation.', $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve all active operation master records, ordered by operation code.
     *
     * @return JsonResponse
     */
    public function getAll(): JsonResponse
    {
        try {
            $operationMasters = OperationMaster::where('active', true)
                ->orderBy('operation_code', 'asc')
                ->get();

            return $this->success('Operations retrieved successfully.', $operationMasters);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve operations.', $e->getMessage(), 500);
        }
    }

    /**
     * Build a consistent success JSON response.
     *
     * @param string $message
     * @param mixed $data
     * @param int $status
     * @return JsonResponse
     */
    private function success(string $message, $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Build a consistent error JSON response.
     *
     * @param string $message
     * @param mixed $errors
     * @param int $status
     * @return JsonResponse
     */
    private function error(string $message, $errors = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
