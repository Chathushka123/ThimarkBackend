<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

class DashboardController extends Controller
{
    private DashboardService $service;

    public function __construct(DashboardService $service)
    {
        $this->service = $service;
    }

    public function procurementSummary(Request $request): JsonResponse
    {
        return $this->run($request, $this->commonProcurementRules(), fn($validated) => $this->ok($this->service->procurementSummary($validated)));
    }

    public function procurementSpendBySupplier(Request $request): JsonResponse
    {
        $rules = $this->commonProcurementRules();
        $rules['limit'] = 'nullable|integer|min:1|max:50';

        return $this->run($request, $rules, fn($validated) => $this->ok($this->service->procurementSpendBySupplier($validated)));
    }

    public function procurementSpendByCategory(Request $request): JsonResponse
    {
        return $this->run($request, $this->commonProcurementRules(), fn($validated) => $this->ok($this->service->procurementSpendByCategory($validated)));
    }

    public function procurementTrend(Request $request): JsonResponse
    {
        $rules = [
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'supplier_ids' => 'nullable|string',
            'status' => 'nullable|string',
        ];

        return $this->run($request, $rules, fn($validated) => $this->ok($this->service->procurementTrend($validated)));
    }

    public function procurementOrders(Request $request): JsonResponse
    {
        $rules = $this->commonProcurementRules();
        $rules = array_merge($rules, $this->paginationRules(), [
            'sort_by' => 'nullable|string|in:order_date,total_amount,po_number',
            'sort_dir' => 'nullable|string|in:asc,desc',
            'search' => 'nullable|string',
        ]);

        return $this->run($request, $rules, function ($validated) {
            $result = $this->service->procurementOrders($validated);
            return $this->ok($result['data'], $result['meta']);
        });
    }

    public function inventorySummary(Request $request): JsonResponse
    {
        return $this->run($request, $this->commonInventoryRules(), fn($validated) => $this->ok($this->service->inventorySummary($validated)));
    }

    public function inventoryItems(Request $request): JsonResponse
    {
        $rules = array_merge($this->commonInventoryRules(), $this->paginationRules(), [
            'search' => 'nullable|string',
            'sort_by' => 'nullable|string|in:available_qty,stock_value,name',
            'sort_dir' => 'nullable|string|in:asc,desc',
        ]);

        return $this->run($request, $rules, function ($validated) {
            $result = $this->service->inventoryItems($validated);
            return $this->ok($result['data'], $result['meta']);
        });
    }

    public function inventoryLowStock(Request $request): JsonResponse
    {
        $rules = array_merge($this->commonInventoryRules(), $this->paginationRules());

        return $this->run($request, $rules, function ($validated) {
            $result = $this->service->inventoryLowStock($validated);
            return $this->ok($result['data'], $result['meta']);
        });
    }

    public function consumptionSummary(Request $request): JsonResponse
    {
        return $this->run($request, $this->commonConsumptionRules(), fn($validated) => $this->ok($this->service->consumptionSummary($validated)));
    }

    public function consumptionByBatch(Request $request): JsonResponse
    {
        $rules = array_merge($this->commonConsumptionRules(), $this->paginationRules(), [
            'sort_by' => 'nullable|string|in:total_value,total_qty,mrn_count',
            'sort_dir' => 'nullable|string|in:asc,desc',
        ]);

        return $this->run($request, $rules, function ($validated) {
            $result = $this->service->consumptionByBatch($validated);
            return $this->ok($result['data'], $result['meta']);
        });
    }

    public function consumptionByMaterial(Request $request): JsonResponse
    {
        $rules = array_merge($this->commonConsumptionRules(), [
            'limit' => 'nullable|integer|min:1|max:100',
            'category' => 'nullable|string',
        ]);

        return $this->run($request, $rules, fn($validated) => $this->ok($this->service->consumptionByMaterial($validated)));
    }

    public function consumptionReturnables(Request $request): JsonResponse
    {
        $rules = array_merge([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'issued_to' => 'nullable|integer',
        ], $this->paginationRules());

        return $this->run($request, $rules, function ($validated) {
            $result = $this->service->consumptionReturnables($validated);
            return $this->ok($result['data'], $result['meta']);
        });
    }

    public function grnSummary(Request $request): JsonResponse
    {
        return $this->run($request, $this->commonGrnRules(), fn($validated) => $this->ok($this->service->grnSummary($validated)));
    }

    public function grnBySupplier(Request $request): JsonResponse
    {
        $rules = $this->commonGrnRules();
        $rules['limit'] = 'nullable|integer|min:1|max:50';

        return $this->run($request, $rules, fn($validated) => $this->ok($this->service->grnBySupplier($validated)));
    }

    public function grnOpenStuck(Request $request): JsonResponse
    {
        $rules = array_merge([
            'days_threshold' => 'nullable|integer|min:1',
            'warehouse_ids' => 'nullable|string',
        ], $this->paginationRules());

        return $this->run($request, $rules, function ($validated) {
            $result = $this->service->grnOpenStuck($validated);
            return $this->ok($result['data'], $result['meta']);
        });
    }

    public function grnPriceVariance(Request $request): JsonResponse
    {
        $rules = array_merge([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'supplier_ids' => 'nullable|string',
            'warehouse_ids' => 'nullable|string',
        ], $this->paginationRules());

        return $this->run($request, $rules, function ($validated) {
            $result = $this->service->grnPriceVariance($validated);
            return $this->ok($result['data'], $result['meta']);
        });
    }

    public function grnList(Request $request): JsonResponse
    {
        $rules = array_merge($this->commonGrnRules(), $this->paginationRules(), [
            'search' => 'nullable|string',
            'sort_by' => 'nullable|string|in:created_at,status',
            'sort_dir' => 'nullable|string|in:asc,desc',
        ]);

        return $this->run($request, $rules, function ($validated) {
            $result = $this->service->grnList($validated);
            return $this->ok($result['data'], $result['meta']);
        });
    }

    public function paymentsSummary(Request $request): JsonResponse
    {
        return $this->run($request, $this->commonPaymentsRules(), fn($validated) => $this->ok($this->service->paymentsSummary($validated)));
    }

    public function paymentsBySupplier(Request $request): JsonResponse
    {
        $rules = $this->commonPaymentsRules();
        $rules['limit'] = 'nullable|integer|min:1|max:50';

        return $this->run($request, $rules, fn($validated) => $this->ok($this->service->paymentsBySupplier($validated)));
    }

    public function paymentsTrend(Request $request): JsonResponse
    {
        $rules = [
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'supplier_ids' => 'nullable|string',
        ];

        return $this->run($request, $rules, fn($validated) => $this->ok($this->service->paymentsTrend($validated)));
    }

    public function paymentsList(Request $request): JsonResponse
    {
        $rules = array_merge($this->commonPaymentsRules(), $this->paginationRules(), [
            'search' => 'nullable|string',
            'sort_by' => 'nullable|string|in:order_date,total_amount,outstanding',
            'sort_dir' => 'nullable|string|in:asc,desc',
        ]);

        return $this->run($request, $rules, function ($validated) {
            $result = $this->service->paymentsList($validated);
            return $this->ok($result['data'], $result['meta']);
        });
    }

    public function filtersWarehouses(): JsonResponse
    {
        return $this->ok($this->service->filtersWarehouses());
    }

    public function filtersSuppliers(): JsonResponse
    {
        return $this->ok($this->service->filtersSuppliers());
    }

    public function filtersCategories(): JsonResponse
    {
        return $this->ok($this->service->filtersCategories());
    }

    public function filtersBatches(): JsonResponse
    {
        return $this->ok($this->service->filtersBatches());
    }

    public function filtersUsers(): JsonResponse
    {
        return $this->ok($this->service->filtersUsers());
    }

    private function run(Request $request, array $rules, callable $handler): JsonResponse
    {
        $validator = Validator::make($request->query(), $rules);
        if ($validator->fails()) {
            return $this->error('Validation failed.', $validator->errors()->toArray(), 422);
        }

        try {
            return $handler($validator->validated());
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), [], 422);
        } catch (Throwable $e) {
            return $this->error('Internal server error.', ['exception' => $e->getMessage()], 500);
        }
    }

    private function ok($data, array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => (object) $meta,
        ]);
    }

    private function error(string $message, array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => (object) $errors,
        ], $status);
    }

    private function commonProcurementRules(): array
    {
        return [
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'supplier_ids' => 'nullable|string',
            'status' => 'nullable|string',
            'category' => 'nullable|string',
            'created_by' => 'nullable|integer',
            'min_amount' => 'nullable|numeric|min:0',
        ];
    }

    private function commonInventoryRules(): array
    {
        return [
            'warehouse_ids' => 'nullable|string',
            'category' => 'nullable|string',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'low_stock_only' => 'nullable|boolean',
            'active_only' => 'nullable|boolean',
        ];
    }

    private function commonConsumptionRules(): array
    {
        return [
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'batch_ids' => 'nullable|string',
            'status' => 'nullable|string',
            'issued_to' => 'nullable|integer',
            'warehouse_ids' => 'nullable|string',
            'model_id' => 'nullable|integer',
        ];
    }

    private function commonGrnRules(): array
    {
        return [
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'supplier_ids' => 'nullable|string',
            'warehouse_ids' => 'nullable|string',
            'status' => 'nullable|string',
            'price_variance_only' => 'nullable|boolean',
        ];
    }

    private function commonPaymentsRules(): array
    {
        return [
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'supplier_ids' => 'nullable|string',
            'po_status' => 'nullable|string',
            'unpaid_only' => 'nullable|boolean',
            'payment_date_from' => 'nullable|date_format:Y-m-d',
            'payment_date_to' => 'nullable|date_format:Y-m-d',
            'min_outstanding' => 'nullable|numeric|min:0',
        ];
    }

    private function paginationRules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
