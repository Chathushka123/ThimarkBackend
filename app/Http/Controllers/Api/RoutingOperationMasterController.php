<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoutingOperationMasterRequest;
use App\Http\Requests\UpdateRoutingOperationMasterRequest;
use App\RoutingOperationMaster;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Handles CRUD operations for the RoutingOperationMaster pivot table
 * (routing <-> operation_masters mapping).
 */
class RoutingOperationMasterController extends Controller
{
    /**
     * Create a new routing operation master record.
     *
     * @param StoreRoutingOperationMasterRequest $request
     * @return JsonResponse
     */
    public function createRec(StoreRoutingOperationMasterRequest $request): JsonResponse
    {
        try {
            $routingOperationMaster = RoutingOperationMaster::create([
                'routing_id' => $request->input('routing_id'),
                'operation_id' => $request->input('operation_id'),
                'smv' => $request->input('smv'),
                'seq' => $request->input('seq'),
                'in' => $request->boolean('in', true),
                'out' => $request->boolean('out', true),
                'active' => $request->boolean('active', true),
            ]);

            return $this->success('Routing operation created successfully.', $routingOperationMaster, 201);
        } catch (Exception $e) {
            return $this->error('Failed to create routing operation.', $e->getMessage(), 500);
        }
    }

    /**
     * Update an existing routing operation master record.
     *
     * @param UpdateRoutingOperationMasterRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateRec(UpdateRoutingOperationMasterRequest $request, int $id): JsonResponse
    {
        try {
            $routingOperationMaster = RoutingOperationMaster::find($id);

            if (!$routingOperationMaster) {
                return $this->error('Routing operation not found.', null, 404);
            }

            $routingOperationMaster->update([
                'routing_id' => $request->input('routing_id'),
                'operation_id' => $request->input('operation_id'),
                'smv' => $request->input('smv'),
                'seq' => $request->input('seq'),
                'in' => $request->boolean('in', $routingOperationMaster->in),
                'out' => $request->boolean('out', $routingOperationMaster->out),
                'active' => $request->boolean('active', $routingOperationMaster->active),
            ]);

            return $this->success('Routing operation updated successfully.', $routingOperationMaster);
        } catch (Exception $e) {
            return $this->error('Failed to update routing operation.', $e->getMessage(), 500);
        }
    }

    /**
     * Soft delete a routing operation master record by marking it inactive.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function deleteRec(int $id): JsonResponse
    {
        try {
            $routingOperationMaster = RoutingOperationMaster::find($id);

            if (!$routingOperationMaster) {
                return $this->error('Routing operation not found.', null, 404);
            }

            $routingOperationMaster->active = false;
            $routingOperationMaster->save();

            return $this->success('Routing operation deactivated successfully.', $routingOperationMaster);
        } catch (Exception $e) {
            return $this->error('Failed to deactivate routing operation.', $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve a single routing operation master record.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getOne(int $id): JsonResponse
    {
        try {
            $routingOperationMaster = RoutingOperationMaster::find($id);

            if (!$routingOperationMaster) {
                return $this->error('Routing operation not found.', null, 404);
            }

            return $this->success('Routing operation retrieved successfully.', $routingOperationMaster);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve routing operation.', $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve all active routing operation master records, ordered by sequence.
     *
     * @return JsonResponse
     */
    public function getAll(): JsonResponse
    {
        try {
            $routingOperationMasters = RoutingOperationMaster::where('active', true)
                ->orderBy('routing_id', 'asc')
                ->orderBy('seq', 'asc')
                ->get();

            return $this->success('Routing operations retrieved successfully.', $routingOperationMasters);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve routing operations.', $e->getMessage(), 500);
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
