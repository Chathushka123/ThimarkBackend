<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Repositories\WorkOrderRepository;
use App\Http\Requests\DeleteBundleDetailRequest;
use App\Http\Requests\StoreBundleDetailRequest;
use App\Http\Requests\StoreWorkOrderBundleRequest;
use App\Http\Requests\StoreWorkOrderRequest;
use App\Http\Requests\UpdateWorkOrderBundleRequest;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Handles the Work Order screen: creating a work order from a batch detail
 * (generating its routing operations), picking bundle material against
 * scanned warehouse stock, and finalizing/reopening the work order for
 * production WIP scanning.
 */
class WorkOrderController extends Controller
{
    private WorkOrderRepository $repository;

    public function __construct(WorkOrderRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Batch details available to pick from when creating a work order.
     */
    public function batchDetailsList(): JsonResponse
    {
        try {
            $batchDetails = $this->repository->batchDetailsList();

            return $this->success('Batch details retrieved successfully.', $batchDetails);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve batch details.', $e->getMessage(), 500);
        }
    }

    /**
     * List work orders, optionally filtered by status (OPEN/FINALIZED).
     */
    public function getAll(Request $request): JsonResponse
    {
        try {
            $workOrders = $this->repository->all($request->query('status'));

            return $this->success('Work orders retrieved successfully.', $workOrders);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve work orders.', $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve a single work order with its routing operations and bundles.
     */
    public function getOne(int $id): JsonResponse
    {
        try {
            $workOrder = $this->repository->find($id);

            if (!$workOrder) {
                return $this->error('Work order not found.', null, 404);
            }

            return $this->success('Work order retrieved successfully.', $workOrder);
        } catch (Exception $e) {
            return $this->error('Failed to retrieve work order.', $e->getMessage(), 500);
        }
    }

    /**
     * Create a work order from a batch detail.
     */
    public function createRec(StoreWorkOrderRequest $request): JsonResponse
    {
        try {
            $workOrder = $this->repository->create((int) $request->input('batch_detail_id'));

            return $this->success('Work order created successfully.', $this->repository->find($workOrder->id), 201);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return $this->error('Failed to create work order.', $e->getMessage(), 500);
        }
    }

    /**
     * Add a bundle (size/qty lot) to an open work order.
     */
    public function createBundle(StoreWorkOrderBundleRequest $request): JsonResponse
    {
        try {
            $bundle = $this->repository->createBundle(
                (int) $request->input('work_order_id'),
                $request->input('size'),
                (int) $request->input('qty'),
                $request->filled('trolly_master_id') ? (int) $request->input('trolly_master_id') : null
            );

            return $this->success('Bundle added successfully.', $bundle, 201);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return $this->error('Failed to add bundle.', $e->getMessage(), 500);
        }
    }

    /**
     * Update a bundle's size/qty while its work order is still open.
     */
    public function updateBundle(UpdateWorkOrderBundleRequest $request, int $id): JsonResponse
    {
        try {
            $bundle = $this->repository->updateBundle(
                $id,
                $request->input('size'),
                (int) $request->input('qty'),
                $request->filled('trolly_master_id') ? (int) $request->input('trolly_master_id') : null
            );

            return $this->success('Bundle updated successfully.', $bundle);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return $this->error('Failed to update bundle.', $e->getMessage(), 500);
        }
    }

    /**
     * Pick material for a bundle from a scanned warehouse stock row.
     */
    public function addBundleDetail(StoreBundleDetailRequest $request): JsonResponse
    {
        try {
            $detail = $this->repository->addBundleDetail(
                (int) $request->input('bundle_id'),
                (int) $request->input('whl_item_id'),
                (int) $request->input('qty')
            );

            return $this->success('Material picked successfully.', $detail->load(['stockMaterial', 'whlItem.warehouseLocation']), 201);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return $this->error('Failed to pick material.', $e->getMessage(), 500);
        }
    }

    /**
     * Delete a bundle detail pick, reversing its qty back onto stock.
     */
    public function deleteBundleDetail(DeleteBundleDetailRequest $request): JsonResponse
    {
        try {
            $this->repository->deleteBundleDetail((int) $request->input('id'));

            return $this->success('Pick removed successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return $this->error('Failed to remove pick.', $e->getMessage(), 500);
        }
    }

    /**
     * Finalize a work order, generating bundle tickets for production
     * WIP scanning.
     */
    public function finalize(int $id): JsonResponse
    {
        try {
            $this->repository->finalize($id);

            return $this->success('Work order finalized successfully.', $this->repository->find($id));
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return $this->error('Failed to finalize work order.', $e->getMessage(), 500);
        }
    }

    /**
     * Reopen a finalized work order, removing its bundle tickets — unless
     * production scanning has already started.
     */
    public function reopen(int $id): JsonResponse
    {
        try {
            $this->repository->reopen($id);

            return $this->success('Work order reopened successfully.', $this->repository->find($id));
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return $this->error('Failed to reopen work order.', $e->getMessage(), 500);
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
