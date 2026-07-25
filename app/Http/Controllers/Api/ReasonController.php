<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReasonRequest;
use App\Http\Requests\UpdateReasonRequest;
use App\Models\Reason;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles CRUD operations for the Reason (reject/rework reason) master
 * table used by the Production WIP Scanning screen's dropdowns.
 */
class ReasonController extends Controller
{
    /**
     * Create a new reason record.
     */
    public function createRec(StoreReasonRequest $request): JsonResponse
    {
        try {
            $reason = Reason::create([
                'code' => $request->input('code'),
                'description' => $request->input('description'),
                'type' => $request->input('type'),
                'active' => $request->boolean('active', true),
            ]);

            return $this->success('Reason created successfully.', $reason, 201);
        } catch (Exception $e) {
            return $this->error('Failed to create reason.', $e->getMessage(), 500);
        }
    }

    /**
     * Update an existing reason record.
     */
    public function updateRec(UpdateReasonRequest $request, int $id): JsonResponse
    {
        try {
            $reason = Reason::find($id);

            if (!$reason) {
                return $this->error('Reason not found.', null, 404);
            }

            $reason->update([
                'code' => $request->input('code'),
                'description' => $request->input('description'),
                'type' => $request->input('type'),
                'active' => $request->boolean('active', $reason->active),
            ]);

            return $this->success('Reason updated successfully.', $reason);
        } catch (Exception $e) {
            return $this->error('Failed to update reason.', $e->getMessage(), 500);
        }
    }

    /**
     * Soft delete a reason record by marking it inactive.
     */
    public function deleteRec(int $id): JsonResponse
    {
        try {
            $reason = Reason::find($id);

            if (!$reason) {
                return $this->error('Reason not found.', null, 404);
            }

            $reason->active = false;
            $reason->save();

            return $this->success('Reason deactivated successfully.', $reason);
        } catch (Exception $e) {
            return $this->error('Failed to deactivate reason.', $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve a single reason record.
     */
    public function getOne(int $id): JsonResponse
    {
        try {
            $reason = Reason::find($id);

            if (!$reason) {
                return $this->error('Reason not found.', null, 404);
            }

            return $this->success('Reason retrieved successfully.', $reason);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve reason.', $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve all reason records, optionally filtered by type, ordered by
     * description.
     */
    public function getAll(Request $request): JsonResponse
    {
        try {
            $query = Reason::query();

            if ($request->filled('type')) {
                $query->where('type', strtoupper($request->input('type')));
            }

            $reasons = $query->orderBy('description', 'asc')->get();

            return $this->success('Reasons retrieved successfully.', $reasons);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve reasons.', $e->getMessage(), 500);
        }
    }

    /**
     * Build a consistent success JSON response.
     *
     * @param mixed $data
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
     * @param mixed $errors
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
