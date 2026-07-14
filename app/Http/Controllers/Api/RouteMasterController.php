<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRouteMasterRequest;
use App\Http\Requests\UpdateRouteMasterRequest;
use App\RouteMaster;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Handles CRUD operations for the RouteMaster (routing) master table.
 */
class RouteMasterController extends Controller
{
    /**
     * Create a new routing master record.
     *
     * @param StoreRouteMasterRequest $request
     * @return JsonResponse
     */
    public function createRec(StoreRouteMasterRequest $request): JsonResponse
    {
        try {
            $routeMaster = RouteMaster::create([
                'route_code' => $request->input('route_code'),
                'description' => $request->input('description'),
                'active' => $request->boolean('active', true),
            ]);

            return $this->success('Routing created successfully.', $routeMaster, 201);
        } catch (Exception $e) {
            return $this->error('Failed to create routing.', $e->getMessage(), 500);
        }
    }

    /**
     * Update an existing routing master record.
     *
     * @param UpdateRouteMasterRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateRec(UpdateRouteMasterRequest $request, int $id): JsonResponse
    {
        try {
            $routeMaster = RouteMaster::find($id);

            if (!$routeMaster) {
                return $this->error('Routing not found.', null, 404);
            }

            $routeMaster->update([
                'route_code' => $request->input('route_code'),
                'description' => $request->input('description'),
                'active' => $request->boolean('active', $routeMaster->active),
            ]);

            return $this->success('Routing updated successfully.', $routeMaster);
        } catch (Exception $e) {
            return $this->error('Failed to update routing.', $e->getMessage(), 500);
        }
    }

    /**
     * Soft delete a routing master record by marking it inactive.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function deleteRec(int $id): JsonResponse
    {
        try {
            $routeMaster = RouteMaster::find($id);

            if (!$routeMaster) {
                return $this->error('Routing not found.', null, 404);
            }

            $routeMaster->active = false;
            $routeMaster->save();

            return $this->success('Routing deactivated successfully.', $routeMaster);
        } catch (Exception $e) {
            return $this->error('Failed to deactivate routing.', $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve a single routing master record.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getOne(int $id): JsonResponse
    {
        try {
            $routeMaster = RouteMaster::find($id);

            if (!$routeMaster) {
                return $this->error('Routing not found.', null, 404);
            }

            return $this->success('Routing retrieved successfully.', $routeMaster);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve routing.', $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve all active routing master records, ordered by route code.
     *
     * @return JsonResponse
     */
    public function getAll(): JsonResponse
    {
        try {
            $routeMasters = RouteMaster::where('active', true)
                ->orderBy('route_code', 'asc')
                ->get();

            return $this->success('Routings retrieved successfully.', $routeMasters);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve routings.', $e->getMessage(), 500);
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
