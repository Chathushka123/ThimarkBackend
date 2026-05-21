<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DashboardService
{
    private const PO_STATUSES = ['DRAFT', 'OPEN', 'PENDING APPROVAL', 'APPROVED', 'SENT', 'RECEIVED', 'CLOSED', 'CANCELLED'];
    private const MRN_STATUSES = ['open', 'finalized', 'complete'];
    private const GRN_STATUSES = ['open', 'completed'];

    public function procurementSummary(array $params): array
    {
        $query = $this->buildProcurementPoQuery($params, true);

        $summary = (clone $query)
            ->selectRaw('COALESCE(SUM(po.total_amount), 0) AS total_po_value')
            ->selectRaw('COUNT(*) AS total_po_count')
            ->selectRaw('COALESCE(AVG(po.total_amount), 0) AS avg_order_value')
            ->selectRaw("COALESCE(SUM(CASE WHEN po.status = 'APPROVED' THEN po.total_amount ELSE 0 END), 0) AS approved_value")
            ->selectRaw("COALESCE(SUM(CASE WHEN po.status = 'DRAFT' THEN po.total_amount ELSE 0 END), 0) AS draft_value")
            ->selectRaw("COALESCE(SUM(CASE WHEN po.status = 'CANCELLED' THEN po.total_amount ELSE 0 END), 0) AS cancelled_value")
            ->first();

        $avgCycleDays = (clone $query)
            ->where('po.status', 'RECEIVED')
            ->selectRaw('COALESCE(AVG(DATEDIFF(po.updated_at, po.order_date)), 0) AS avg_cycle_days')
            ->value('avg_cycle_days');

        $statusBreakdown = (clone $query)
            ->select('po.status')
            ->selectRaw('COUNT(*) AS cnt')
            ->selectRaw('COALESCE(SUM(po.total_amount), 0) AS total_value')
            ->groupBy('po.status')
            ->orderByDesc('total_value')
            ->get()
            ->map(function ($row) {
                return [
                    'status' => $row->status,
                    'count' => (int) $row->cnt,
                    'total_value' => $this->money($row->total_value),
                ];
            })
            ->values()
            ->all();

        return [
            'total_po_value' => $this->money($summary->total_po_value ?? 0),
            'total_po_count' => (int) ($summary->total_po_count ?? 0),
            'avg_order_value' => $this->money($summary->avg_order_value ?? 0),
            'approved_value' => $this->money($summary->approved_value ?? 0),
            'draft_value' => $this->money($summary->draft_value ?? 0),
            'cancelled_value' => $this->money($summary->cancelled_value ?? 0),
            'avg_cycle_days' => round((float) $avgCycleDays, 2),
            'status_breakdown' => $statusBreakdown,
        ];
    }

    public function procurementSpendBySupplier(array $params): array
    {
        $limit = $this->limit($params, 10, 50);
        $query = $this->buildProcurementPoQuery($params, true);

        $totalValue = (float) ((clone $query)->sum('po.total_amount') ?? 0);

        $rows = (clone $query)
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->select('s.id as supplier_id', 's.name as supplier_name')
            ->selectRaw('COUNT(*) AS po_count')
            ->selectRaw('COALESCE(SUM(po.total_amount), 0) AS total_value')
            ->groupBy('s.id', 's.name')
            ->orderByDesc('total_value')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) use ($totalValue) {
            $rowValue = (float) $row->total_value;
            return [
                'supplier_id' => (int) $row->supplier_id,
                'supplier_name' => $row->supplier_name,
                'po_count' => (int) $row->po_count,
                'total_value' => $this->money($rowValue),
                'percentage_of_total' => $totalValue > 0 ? round(($rowValue / $totalValue) * 100, 2) : 0.0,
            ];
        })->values()->all();
    }

    public function procurementSpendByCategory(array $params): array
    {
        $query = $this->buildProcurementPoQuery($params, false);

        $rows = (clone $query)
            ->join('purchase_order_items as poi', 'poi.purchase_order_id', '=', 'po.id')
            ->join('stock_materials as sm', function ($join) {
                $join->on('sm.id', '=', 'poi.material_id')->where('sm.active', '=', 1);
            })
            ->select('sm.category')
            ->selectRaw('COUNT(DISTINCT po.id) AS po_count')
            ->selectRaw('COALESCE(SUM(poi.total), 0) AS total_value')
            ->groupBy('sm.category')
            ->orderByDesc('total_value')
            ->get();

        $grandTotal = (float) $rows->sum('total_value');

        return $rows->map(function ($row) use ($grandTotal) {
            $value = (float) $row->total_value;
            return [
                'category' => $row->category,
                'po_count' => (int) $row->po_count,
                'total_value' => $this->money($value),
                'percentage_of_total' => $grandTotal > 0 ? round(($value / $grandTotal) * 100, 2) : 0.0,
            ];
        })->values()->all();
    }

    public function procurementTrend(array $params): array
    {
        [$dateFrom, $dateTo] = $this->resolveRange($params, 'date_from', 'date_to', 'year');

        $query = DB::table('purchase_orders as po')
            ->whereBetween('po.order_date', [$dateFrom, $dateTo]);

        $supplierIds = $this->csvInts($params['supplier_ids'] ?? null, 'supplier_ids');
        if (!empty($supplierIds)) {
            $query->whereIn('po.supplier_id', $supplierIds);
        }

        $statuses = $this->csvStrings($params['status'] ?? null, 'status');
        if (!empty($statuses)) {
            $statuses = $this->validateEnumArray($statuses, self::PO_STATUSES, 'status', true);
            $query->whereIn('po.status', $statuses);
        }

        return $query
            ->selectRaw("DATE_FORMAT(po.order_date, '%Y-%m') AS month")
            ->selectRaw('COUNT(*) AS po_count')
            ->selectRaw('COALESCE(SUM(po.total_amount), 0) AS total_value')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($row) {
                return [
                    'month' => $row->month,
                    'po_count' => (int) $row->po_count,
                    'total_value' => $this->money($row->total_value),
                ];
            })
            ->values()
            ->all();
    }

    public function procurementOrders(array $params): array
    {
        $sortBy = $this->sortBy($params, ['order_date', 'total_amount', 'po_number'], 'order_date');
        $sortDir = $this->sortDir($params);

        $query = $this->buildProcurementPoQuery($params, true)
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('users as u', 'u.id', '=', 'po.created_by')
            ->leftJoin(
                DB::raw('(SELECT purchase_order_id, COUNT(*) AS item_count FROM purchase_order_items GROUP BY purchase_order_id) poi_cnt'),
                'poi_cnt.purchase_order_id',
                '=',
                'po.id'
            );

        $search = trim((string) ($params['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('po.po_number', 'like', '%' . $search . '%')
                    ->orWhere('s.name', 'like', '%' . $search . '%');
            });
        }

        $query->select(
            'po.id',
            'po.po_number',
            'po.supplier_id',
            's.name as supplier_name',
            'po.order_date',
            'po.expected_delivery_date',
            'po.status',
            'po.total_amount',
            'po.notes',
            'u.name as created_by_name',
            DB::raw('COALESCE(poi_cnt.item_count, 0) as item_count')
        )
            ->orderBy('po.' . $sortBy, $sortDir);

        return $this->paginateAndFormat($query, function ($row) {
            return [
                'id' => (int) $row->id,
                'po_number' => $row->po_number,
                'supplier_id' => (int) $row->supplier_id,
                'supplier_name' => $row->supplier_name,
                'order_date' => $this->dateOnly($row->order_date),
                'expected_delivery_date' => $this->dateOnly($row->expected_delivery_date),
                'status' => $row->status,
                'total_amount' => $this->money($row->total_amount),
                'notes' => $row->notes,
                'created_by_name' => $row->created_by_name,
                'item_count' => (int) $row->item_count,
            ];
        }, $params);
    }

    public function inventorySummary(array $params): array
    {
        $agg = $this->buildInventoryAggregateQuery($params);

        if ($this->toBool($params['low_stock_only'] ?? null)) {
            $agg->havingRaw('sm.min_qty IS NOT NULL AND sm.min_qty > 0 AND SUM(gd.available_qty) <= sm.min_qty');
        }

        $summary = DB::query()->fromSub($agg, 'x')
            ->selectRaw('COUNT(DISTINCT x.stock_item_id) AS total_distinct_items')
            ->selectRaw('COALESCE(SUM(x.total_qty_received), 0) AS total_qty_received')
            ->selectRaw('COALESCE(SUM(x.available_qty), 0) AS total_qty_available')
            ->selectRaw('COALESCE(SUM(x.stock_value), 0) AS total_stock_value')
            ->selectRaw('SUM(CASE WHEN x.min_qty IS NOT NULL AND x.min_qty > 0 AND x.available_qty <= x.min_qty THEN 1 ELSE 0 END) AS low_stock_items_count')
            ->selectRaw('SUM(CASE WHEN x.available_qty = 0 THEN 1 ELSE 0 END) AS zero_stock_items_count')
            ->first();

        $warehousesSummary = DB::query()->fromSub($agg, 'x')
            ->select('x.warehouse_id', 'x.warehouse_name')
            ->selectRaw('COUNT(DISTINCT x.stock_item_id) AS item_count')
            ->selectRaw('COALESCE(SUM(x.available_qty), 0) AS total_qty_available')
            ->selectRaw('COALESCE(SUM(x.stock_value), 0) AS total_stock_value')
            ->groupBy('x.warehouse_id', 'x.warehouse_name')
            ->orderBy('x.warehouse_name')
            ->get()
            ->map(function ($row) {
                return [
                    'warehouse_id' => (int) $row->warehouse_id,
                    'warehouse_name' => $row->warehouse_name,
                    'item_count' => (int) $row->item_count,
                    'total_qty_available' => (int) $row->total_qty_available,
                    'total_stock_value' => $this->money($row->total_stock_value),
                ];
            })
            ->values()
            ->all();

        $totalReceived = (float) ($summary->total_qty_received ?? 0);
        $totalAvailable = (float) ($summary->total_qty_available ?? 0);

        return [
            'total_distinct_items' => (int) ($summary->total_distinct_items ?? 0),
            'total_qty_received' => (int) $totalReceived,
            'total_qty_available' => (int) $totalAvailable,
            'utilisation_pct' => $totalReceived > 0 ? round(($totalAvailable / $totalReceived) * 100, 2) : 0.0,
            'total_stock_value' => $this->money($summary->total_stock_value ?? 0),
            'low_stock_items_count' => (int) ($summary->low_stock_items_count ?? 0),
            'zero_stock_items_count' => (int) ($summary->zero_stock_items_count ?? 0),
            'warehouses_summary' => $warehousesSummary,
        ];
    }

    public function inventoryItems(array $params): array
    {
        $sortBy = $this->sortBy($params, ['available_qty', 'stock_value', 'name'], 'available_qty');
        $sortDir = $this->sortDir($params);

        $agg = DB::query()->fromSub($this->buildInventoryAggregateQuery($params), 'x');

        $search = trim((string) ($params['search'] ?? ''));
        if ($search !== '') {
            $agg->where(function ($q) use ($search) {
                $q->where('x.name', 'like', '%' . $search . '%')
                    ->orWhere('x.code', 'like', '%' . $search . '%');
            });
        }

        if ($sortBy === 'name') {
            $agg->orderBy('x.name', $sortDir);
        } else {
            $agg->orderBy('x.' . $sortBy, $sortDir);
        }

        return $this->paginateAndFormat($agg, function ($row) {
            $received = (float) $row->total_qty_received;
            $available = (float) $row->available_qty;
            return [
                'stock_item_id' => (int) $row->stock_item_id,
                'material_name' => $row->name,
                'material_code' => $row->code,
                'category' => $row->category,
                'warehouse_id' => (int) $row->warehouse_id,
                'warehouse_name' => $row->warehouse_name,
                'total_qty_received' => (int) $received,
                'available_qty' => (int) $available,
                'utilisation_pct' => $received > 0 ? round(($available / $received) * 100, 2) : 0.0,
                'latest_grn_price' => $this->money($row->latest_grn_price),
                'stock_value' => $this->money($row->stock_value),
                'min_qty' => is_null($row->min_qty) ? null : (int) $row->min_qty,
                'is_low_stock' => !is_null($row->min_qty) && (float) $row->min_qty > 0 && $available <= (float) $row->min_qty,
                'last_received_date' => $this->dateOnly($row->last_received_date),
            ];
        }, $params);
    }

    public function inventoryLowStock(array $params): array
    {
        $agg = DB::query()->fromSub($this->buildInventoryAggregateQuery($params), 'x')
            ->whereRaw('x.min_qty IS NOT NULL AND x.min_qty > 0 AND x.available_qty <= x.min_qty')
            ->orderBy('x.available_qty')
            ->orderBy('x.name');

        return $this->paginateAndFormat($agg, function ($row) {
            $received = (float) $row->total_qty_received;
            $available = (float) $row->available_qty;
            $minQty = (float) $row->min_qty;
            return [
                'stock_item_id' => (int) $row->stock_item_id,
                'material_name' => $row->name,
                'material_code' => $row->code,
                'category' => $row->category,
                'warehouse_id' => (int) $row->warehouse_id,
                'warehouse_name' => $row->warehouse_name,
                'total_qty_received' => (int) $received,
                'available_qty' => (int) $available,
                'utilisation_pct' => $received > 0 ? round(($available / $received) * 100, 2) : 0.0,
                'latest_grn_price' => $this->money($row->latest_grn_price),
                'stock_value' => $this->money($row->stock_value),
                'min_qty' => (int) $row->min_qty,
                'is_low_stock' => true,
                'shortage_qty' => (int) max(0, $minQty - $available),
                'last_received_date' => $this->dateOnly($row->last_received_date),
            ];
        }, $params);
    }

    public function consumptionSummary(array $params): array
    {
        $base = $this->buildMrnBaseQuery($params);

        $summary = (clone $base)
            ->selectRaw('COUNT(DISTINCT m.id) AS total_mrns')
            ->selectRaw('COALESCE(SUM(md.issued_qty), 0) AS total_qty_issued')
            ->selectRaw('COALESCE(SUM(md.issued_qty * md.grn_price), 0) AS total_consumption_value')
            ->selectRaw("COUNT(DISTINCT CASE WHEN m.status = 'open' THEN m.id END) AS open_mrns")
            ->selectRaw("COUNT(DISTINCT CASE WHEN m.status = 'finalized' THEN m.id END) AS finalized_mrns")
            ->selectRaw("COUNT(DISTINCT CASE WHEN m.status = 'complete' THEN m.id END) AS complete_mrns")
            ->selectRaw('COUNT(DISTINCT CASE WHEN b.active = 1 THEN b.id END) AS total_batches_active')
            ->first();

        $top = (clone $base)
            ->join('stock_materials as sm', function ($join) {
                $join->on('sm.id', '=', 'md.stock_item_id')->where('sm.active', '=', 1);
            })
            ->select('md.stock_item_id', 'sm.name')
            ->selectRaw('COALESCE(SUM(md.issued_qty), 0) AS total_qty_issued')
            ->selectRaw('COALESCE(SUM(md.issued_qty * md.grn_price), 0) AS total_value')
            ->groupBy('md.stock_item_id', 'sm.name')
            ->orderByDesc('total_value')
            ->first();

        $returnablesPending = DB::table('returnables as r')
            ->where('r.active', 1)
            ->whereRaw('COALESCE(r.issued_qty, 0) > COALESCE(r.return_qty, 0)');

        if (!empty($params['issued_to'])) {
            $returnablesPending->where('r.issued_to', (int) $params['issued_to']);
        }

        [$dateFrom, $dateTo] = $this->resolveRange($params, 'date_from', 'date_to', 'month');
        $returnablesPending->whereBetween('r.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        $totalMrns = (int) ($summary->total_mrns ?? 0);
        $totalValue = (float) ($summary->total_consumption_value ?? 0);

        return [
            'total_mrns' => $totalMrns,
            'total_qty_issued' => (int) ($summary->total_qty_issued ?? 0),
            'total_consumption_value' => $this->money($totalValue),
            'open_mrns' => (int) ($summary->open_mrns ?? 0),
            'finalized_mrns' => (int) ($summary->finalized_mrns ?? 0),
            'complete_mrns' => (int) ($summary->complete_mrns ?? 0),
            'total_batches_active' => (int) ($summary->total_batches_active ?? 0),
            'returnables_pending_return' => (int) $returnablesPending->count(),
            'avg_cost_per_mrn' => $totalMrns > 0 ? round($totalValue / $totalMrns, 2) : 0.0,
            'top_consumed_material' => $top ? [
                'stock_item_id' => (int) $top->stock_item_id,
                'name' => $top->name,
                'total_qty_issued' => (int) $top->total_qty_issued,
                'total_value' => $this->money($top->total_value),
            ] : null,
        ];
    }

    public function consumptionByBatch(array $params): array
    {
        $sortBy = $this->sortBy($params, ['total_value', 'total_qty', 'mrn_count'], 'total_value');
        $sortDir = $this->sortDir($params);

        $query = $this->buildMrnBaseQuery($params)
            ->leftJoin('main_models as mm', 'mm.id', '=', 'mo.main_model_id')
            ->select(
                'b.id as batch_id',
                'b.batch_no',
                'b.model_id',
                DB::raw('COALESCE(mm.name, mo.name) as model_name')
            )
            ->selectRaw('COUNT(DISTINCT m.id) AS mrn_count')
            ->selectRaw("COUNT(DISTINCT CASE WHEN m.status = 'open' THEN m.id END) AS open_mrns")
            ->selectRaw('COALESCE(SUM(md.issued_qty), 0) AS total_qty')
            ->selectRaw('COALESCE(SUM(md.issued_qty * md.grn_price), 0) AS total_value')
            ->selectRaw('MAX(m.created_at) AS last_mrn_date')
            ->groupBy('b.id', 'b.batch_no', 'b.model_id', 'mm.name', 'mo.name')
            ->orderBy($sortBy === 'total_qty' ? 'total_qty' : $sortBy, $sortDir);

        return $this->paginateAndFormat($query, function ($row) {
            $mrnCount = (int) $row->mrn_count;
            $totalValue = (float) $row->total_value;
            return [
                'batch_id' => (int) $row->batch_id,
                'batch_no' => $row->batch_no,
                'model_id' => (int) $row->model_id,
                'model_name' => $row->model_name,
                'mrn_count' => $mrnCount,
                'open_mrns' => (int) $row->open_mrns,
                'total_qty_issued' => (int) $row->total_qty,
                'total_consumption_value' => $this->money($totalValue),
                'avg_cost_per_mrn' => $mrnCount > 0 ? round($totalValue / $mrnCount, 2) : 0.0,
                'last_mrn_date' => $this->dateOnly($row->last_mrn_date),
            ];
        }, $params);
    }

    public function consumptionByMaterial(array $params): array
    {
        $limit = $this->limit($params, 20, 100);

        $base = $this->buildMrnBaseQuery($params)
            ->join('stock_materials as sm', function ($join) {
                $join->on('sm.id', '=', 'md.stock_item_id')->where('sm.active', '=', 1);
            });

        $categories = $this->resolveCategories($params['category'] ?? null);
        if (!empty($categories)) {
            $base->whereIn('sm.category', $categories);
        }

        $rows = (clone $base)
            ->select('sm.id as stock_item_id', 'sm.name as material_name', 'sm.code as material_code', 'sm.category')
            ->selectRaw('COALESCE(SUM(md.issued_qty), 0) AS total_qty_issued')
            ->selectRaw('COALESCE(SUM(md.issued_qty * md.grn_price), 0) AS total_value')
            ->selectRaw('COUNT(DISTINCT m.id) AS mrn_count')
            ->groupBy('sm.id', 'sm.name', 'sm.code', 'sm.category')
            ->orderByDesc('total_value')
            ->limit($limit)
            ->get();

        $totalValue = (float) ((clone $base)->selectRaw('COALESCE(SUM(md.issued_qty * md.grn_price), 0) as v')->value('v') ?? 0);

        return $rows->map(function ($row) use ($totalValue) {
            $value = (float) $row->total_value;
            return [
                'stock_item_id' => (int) $row->stock_item_id,
                'material_name' => $row->material_name,
                'material_code' => $row->material_code,
                'category' => $row->category,
                'total_qty_issued' => (int) $row->total_qty_issued,
                'total_value' => $this->money($value),
                'mrn_count' => (int) $row->mrn_count,
                'percentage_of_total_value' => $totalValue > 0 ? round(($value / $totalValue) * 100, 2) : 0.0,
            ];
        })->values()->all();
    }

    public function consumptionReturnables(array $params): array
    {
        [$dateFrom, $dateTo] = $this->resolveRange($params, 'date_from', 'date_to', 'month');

        $query = DB::table('returnables as r')
            ->leftJoin('stock_materials as sm', function ($join) {
                $join->on('sm.id', '=', 'r.stock_item_id')->where('sm.active', '=', 1);
            })
            ->leftJoin('users as u', 'u.id', '=', 'r.issued_to')
            ->where('r.active', 1)
            ->whereBetween('r.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->select(
                'r.id as returnable_id',
                'r.stock_item_id',
                'sm.name as material_name',
                'r.issued_to as issued_to_user_id',
                'u.name as issued_to_name',
                'r.total_qty',
                'r.issued_qty',
                'r.return_qty',
                'r.remarks',
                'r.created_at'
            )
            ->orderByDesc('r.created_at');

        if (!empty($params['issued_to'])) {
            $query->where('r.issued_to', (int) $params['issued_to']);
        }

        return $this->paginateAndFormat($query, function ($row) {
            $issuedQty = (float) ($row->issued_qty ?? 0);
            $returnedQty = (float) ($row->return_qty ?? 0);
            return [
                'returnable_id' => (int) $row->returnable_id,
                'stock_item_id' => is_null($row->stock_item_id) ? null : (int) $row->stock_item_id,
                'material_name' => $row->material_name,
                'issued_to_user_id' => is_null($row->issued_to_user_id) ? null : (int) $row->issued_to_user_id,
                'issued_to_name' => $row->issued_to_name,
                'total_qty' => (float) $row->total_qty,
                'issued_qty' => $issuedQty,
                'return_qty' => $returnedQty,
                'pending_return_qty' => round(max(0, $issuedQty - $returnedQty), 2),
                'remarks' => $row->remarks,
                'created_at' => $this->dateOnly($row->created_at),
            ];
        }, $params);
    }

    public function grnSummary(array $params): array
    {
        $grn = $this->buildGrnBaseQuery($params);

        $summary = (clone $grn)
            ->selectRaw('COUNT(DISTINCT g.id) AS total_grns')
            ->selectRaw("COUNT(DISTINCT CASE WHEN g.status = 'completed' THEN g.id END) AS completed_grns")
            ->selectRaw("COUNT(DISTINCT CASE WHEN g.status = 'open' THEN g.id END) AS open_grns")
            ->selectRaw("COUNT(DISTINCT CASE WHEN g.status = 'open' AND DATEDIFF(NOW(), g.created_at) > 7 THEN g.id END) AS grns_open_over_7_days")
            ->first();

        $lines = $this->buildGrnLinesQuery($params)
            ->selectRaw('COALESCE(SUM(gd.qty), 0) AS total_qty_received')
            ->selectRaw('COALESCE(SUM(gd.qty * gd.grn_price), 0) AS total_grn_value')
            ->first();

        $variance = $this->buildGrnVarianceQuery($params)
            ->selectRaw('COUNT(*) AS price_variance_lines')
            ->selectRaw('COALESCE(SUM(ABS(v.variance) * v.qty), 0) AS price_variance_value')
            ->first();

        $total = (int) ($summary->total_grns ?? 0);
        $completed = (int) ($summary->completed_grns ?? 0);

        return [
            'total_grns' => $total,
            'completed_grns' => $completed,
            'open_grns' => (int) ($summary->open_grns ?? 0),
            'completion_rate_pct' => $total > 0 ? round(($completed / $total) * 100, 2) : 0.0,
            'grns_open_over_7_days' => (int) ($summary->grns_open_over_7_days ?? 0),
            'total_qty_received' => (int) ($lines->total_qty_received ?? 0),
            'total_grn_value' => $this->money($lines->total_grn_value ?? 0),
            'price_variance_lines' => (int) ($variance->price_variance_lines ?? 0),
            'price_variance_value' => $this->money($variance->price_variance_value ?? 0),
        ];
    }

    public function grnBySupplier(array $params): array
    {
        $limit = $this->limit($params, 10, 50);

        $rows = $this->buildGrnLinesQuery($params)
            ->leftJoin('purchase_orders as po', 'po.po_number', '=', 'g.rmpono')
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->whereNotNull('s.id')
            ->select('s.id as supplier_id', 's.name as supplier_name')
            ->selectRaw('COUNT(DISTINCT g.id) AS grn_count')
            ->selectRaw("COUNT(DISTINCT CASE WHEN g.status = 'completed' THEN g.id END) AS completed_count")
            ->selectRaw("COUNT(DISTINCT CASE WHEN g.status = 'open' THEN g.id END) AS open_count")
            ->selectRaw('COALESCE(SUM(gd.qty), 0) AS total_qty_received')
            ->selectRaw('COALESCE(SUM(gd.qty * gd.grn_price), 0) AS total_grn_value')
            ->groupBy('s.id', 's.name')
            ->orderByDesc('total_grn_value')
            ->limit($limit)
            ->get();

        $varianceBySupplier = $this->buildGrnVarianceQuery($params)
            ->leftJoin('suppliers as s', 's.id', '=', 'v.supplier_id')
            ->whereNotNull('s.id')
            ->select('s.id as supplier_id', DB::raw('COUNT(*) AS variance_count'))
            ->groupBy('s.id')
            ->pluck('variance_count', 'supplier_id');

        return $rows->map(function ($row) use ($varianceBySupplier) {
            $grnCount = (int) $row->grn_count;
            $completed = (int) $row->completed_count;
            return [
                'supplier_id' => (int) $row->supplier_id,
                'supplier_name' => $row->supplier_name,
                'grn_count' => $grnCount,
                'completed_count' => $completed,
                'open_count' => (int) $row->open_count,
                'completion_rate_pct' => $grnCount > 0 ? round(($completed / $grnCount) * 100, 2) : 0.0,
                'total_qty_received' => (int) $row->total_qty_received,
                'total_grn_value' => $this->money($row->total_grn_value),
                'price_variance_lines' => (int) ($varianceBySupplier[$row->supplier_id] ?? 0),
            ];
        })->values()->all();
    }

    public function grnOpenStuck(array $params): array
    {
        $daysThreshold = isset($params['days_threshold']) ? (int) $params['days_threshold'] : 7;

        $query = DB::table('grns as g')
            ->join('warehouses as w', 'w.id', '=', 'g.warehouse_id')
            ->leftJoin('users as u', 'u.id', '=', 'g.created_by')
            ->leftJoin('grn_details as gd', function ($join) {
                $join->on('gd.grn_id', '=', 'g.id')->where('gd.active', '=', 1);
            })
            ->where('g.status', 'open')
            ->whereRaw('DATEDIFF(NOW(), g.created_at) > ?', [$daysThreshold])
            ->select(
                'g.id as grn_id',
                'g.rmpono',
                'g.warehouse_id',
                'w.name as warehouse_name',
                'u.name as created_by_name',
                'g.created_at',
                'g.remark'
            )
            ->selectRaw('DATEDIFF(NOW(), g.created_at) AS days_open')
            ->selectRaw('COUNT(gd.id) AS line_count')
            ->groupBy('g.id', 'g.rmpono', 'g.warehouse_id', 'w.name', 'u.name', 'g.created_at', 'g.remark')
            ->orderByDesc('days_open');

        $warehouseIds = $this->csvInts($params['warehouse_ids'] ?? null, 'warehouse_ids');
        if (!empty($warehouseIds)) {
            $query->whereIn('g.warehouse_id', $warehouseIds);
        }

        return $this->paginateAndFormat($query, function ($row) {
            return [
                'grn_id' => (int) $row->grn_id,
                'rmpono' => $row->rmpono,
                'warehouse_id' => (int) $row->warehouse_id,
                'warehouse_name' => $row->warehouse_name,
                'created_by_name' => $row->created_by_name,
                'created_at' => $this->dateOnly($row->created_at),
                'days_open' => (int) $row->days_open,
                'line_count' => (int) $row->line_count,
                'remark' => $row->remark,
            ];
        }, $params);
    }

    public function grnPriceVariance(array $params): array
    {
        $query = $this->buildGrnVarianceQuery($params)
            ->orderByDesc('v.received_date')
            ->orderByDesc('v.grn_detail_id');

        return $this->paginateAndFormat($query, function ($row) {
            $poUnit = (float) $row->po_unit_price;
            $grnPrice = (float) $row->grn_price;
            $variance = (float) $row->variance;
            $qty = (float) $row->qty;

            return [
                'grn_detail_id' => (int) $row->grn_detail_id,
                'grn_id' => (int) $row->grn_id,
                'rmpono' => $row->rmpono,
                'stock_item_id' => (int) $row->stock_item_id,
                'material_name' => $row->material_name,
                'material_code' => $row->material_code,
                'po_unit_price' => $this->money($poUnit),
                'grn_price' => $this->money($grnPrice),
                'variance' => $this->money($variance),
                'variance_pct' => $poUnit != 0.0 ? round(($variance / $poUnit) * 100, 2) : 0.0,
                'qty' => (int) $qty,
                'total_variance_value' => $this->money($variance * $qty),
                'received_date' => $this->dateOnly($row->received_date),
            ];
        }, $params);
    }

    public function grnList(array $params): array
    {
        $sortBy = $this->sortBy($params, ['created_at', 'status'], 'created_at');
        $sortDir = $this->sortDir($params);

        $query = $this->buildGrnBaseQuery($params)
            ->leftJoin('warehouses as w', 'w.id', '=', 'g.warehouse_id')
            ->leftJoin('users as u', 'u.id', '=', 'g.created_by')
            ->leftJoin('grn_details as gd', function ($join) {
                $join->on('gd.grn_id', '=', 'g.id')->where('gd.active', '=', 1);
            })
            ->select(
                'g.id as grn_id',
                'g.rmpono',
                'g.warehouse_id',
                'w.name as warehouse_name',
                'g.status',
                'g.remark',
                'u.name as created_by_name',
                'g.created_at'
            )
            ->selectRaw('COUNT(gd.id) AS line_count')
            ->selectRaw('COALESCE(SUM(gd.qty), 0) AS total_qty')
            ->selectRaw('COALESCE(SUM(gd.qty * gd.grn_price), 0) AS total_value')
            ->groupBy('g.id', 'g.rmpono', 'g.warehouse_id', 'w.name', 'g.status', 'g.remark', 'u.name', 'g.created_at');

        $search = trim((string) ($params['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('g.rmpono', 'like', '%' . $search . '%')
                    ->orWhere('w.name', 'like', '%' . $search . '%');
            });
        }

        $query->orderBy('g.' . $sortBy, $sortDir);

        return $this->paginateAndFormat($query, function ($row) {
            return [
                'grn_id' => (int) $row->grn_id,
                'rmpono' => $row->rmpono,
                'warehouse_id' => (int) $row->warehouse_id,
                'warehouse_name' => $row->warehouse_name,
                'status' => $row->status,
                'remark' => $row->remark,
                'created_by_name' => $row->created_by_name,
                'created_at' => $this->dateOnly($row->created_at),
                'line_count' => (int) $row->line_count,
                'total_qty' => (int) $row->total_qty,
                'total_value' => $this->money($row->total_value),
            ];
        }, $params);
    }

    public function paymentsSummary(array $params): array
    {
        $poBase = $this->buildPayablesPoBaseQuery($params);

        $summary = DB::query()->fromSub($poBase, 'p')
            ->selectRaw('COALESCE(SUM(p.total_amount), 0) AS total_po_value')
            ->selectRaw('COALESCE(SUM(p.total_paid), 0) AS total_paid')
            ->selectRaw('SUM(CASE WHEN p.total_paid > 0 THEN 1 ELSE 0 END) AS pos_with_payment')
            ->selectRaw('SUM(CASE WHEN p.total_paid = 0 THEN 1 ELSE 0 END) AS pos_without_payment')
            ->selectRaw('SUM(CASE WHEN p.total_paid >= p.total_amount THEN 1 ELSE 0 END) AS pos_fully_paid')
            ->selectRaw('SUM(CASE WHEN p.total_paid > 0 AND p.total_paid < p.total_amount THEN 1 ELSE 0 END) AS pos_partially_paid')
            ->first();

        $largest = DB::query()->fromSub($poBase, 'p')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->select('p.supplier_id', 's.name as supplier_name')
            ->selectRaw('COALESCE(SUM(p.total_amount - p.total_paid), 0) AS outstanding')
            ->groupBy('p.supplier_id', 's.name')
            ->orderByDesc('outstanding')
            ->first();

        $totalPoValue = (float) ($summary->total_po_value ?? 0);
        $totalPaid = (float) ($summary->total_paid ?? 0);
        $outstanding = $totalPoValue - $totalPaid;

        return [
            'total_po_value' => $this->money($totalPoValue),
            'total_paid' => $this->money($totalPaid),
            'total_outstanding' => $this->money($outstanding),
            'payment_coverage_pct' => $totalPoValue > 0 ? round(($totalPaid / $totalPoValue) * 100, 2) : 0.0,
            'pos_with_payment' => (int) ($summary->pos_with_payment ?? 0),
            'pos_without_payment' => (int) ($summary->pos_without_payment ?? 0),
            'pos_fully_paid' => (int) ($summary->pos_fully_paid ?? 0),
            'pos_partially_paid' => (int) ($summary->pos_partially_paid ?? 0),
            'largest_outstanding_supplier' => $largest ? [
                'supplier_id' => (int) $largest->supplier_id,
                'supplier_name' => $largest->supplier_name,
                'outstanding' => $this->money($largest->outstanding),
            ] : null,
        ];
    }

    public function paymentsBySupplier(array $params): array
    {
        $limit = $this->limit($params, 10, 50);

        $base = $this->buildPayablesPoBaseQuery($params);

        $rows = DB::query()->fromSub($base, 'p')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->select('p.supplier_id', 's.name as supplier_name')
            ->selectRaw('COUNT(*) AS po_count')
            ->selectRaw('COALESCE(SUM(p.total_amount), 0) AS total_po_value')
            ->selectRaw('COALESCE(SUM(p.total_paid), 0) AS total_paid')
            ->selectRaw('COALESCE(SUM(p.total_amount - p.total_paid), 0) AS outstanding')
            ->selectRaw('MAX(p.order_date) AS latest_po_date')
            ->groupBy('p.supplier_id', 's.name')
            ->orderByDesc('outstanding')
            ->limit($limit)
            ->get();

        $totalOutstanding = (float) $rows->sum('outstanding');

        return $rows->map(function ($row) use ($totalOutstanding) {
            $outstanding = (float) $row->outstanding;
            return [
                'supplier_id' => (int) $row->supplier_id,
                'supplier_name' => $row->supplier_name,
                'po_count' => (int) $row->po_count,
                'total_po_value' => $this->money($row->total_po_value),
                'total_paid' => $this->money($row->total_paid),
                'outstanding' => $this->money($outstanding),
                'outstanding_pct' => $totalOutstanding > 0 ? round(($outstanding / $totalOutstanding) * 100, 2) : 0.0,
                'latest_po_date' => $this->dateOnly($row->latest_po_date),
            ];
        })->values()->all();
    }

    public function paymentsTrend(array $params): array
    {
        [$dateFrom, $dateTo] = $this->resolveRange($params, 'date_from', 'date_to', 'year');

        $poQuery = DB::table('purchase_orders as po')
            ->selectRaw("DATE_FORMAT(po.order_date, '%Y-%m') AS month")
            ->selectRaw('COALESCE(SUM(po.total_amount), 0) AS po_value_raised')
            ->whereBetween('po.order_date', [$dateFrom, $dateTo]);

        $supplierIds = $this->csvInts($params['supplier_ids'] ?? null, 'supplier_ids');
        if (!empty($supplierIds)) {
            $poQuery->whereIn('po.supplier_id', $supplierIds);
        }

        $poByMonth = $poQuery->groupBy('month')->pluck('po_value_raised', 'month');

        $payQuery = DB::table('purchase_order_payments as pop')
            ->join('purchase_orders as po', 'po.id', '=', 'pop.purchase_order_id')
            ->where('pop.active', 1)
            ->whereBetween('po.order_date', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(po.order_date, '%Y-%m') AS month")
            ->selectRaw('COALESCE(SUM(pop.amount), 0) AS amount_paid');

        if (!empty($supplierIds)) {
            $payQuery->whereIn('po.supplier_id', $supplierIds);
        }

        $paidByMonth = $payQuery->groupBy('month')->pluck('amount_paid', 'month');

        $allMonths = collect(array_unique(array_merge(array_keys($poByMonth->toArray()), array_keys($paidByMonth->toArray()))))->sort()->values();

        return $allMonths->map(function ($month) use ($poByMonth, $paidByMonth) {
            return [
                'month' => $month,
                'po_value_raised' => $this->money($poByMonth[$month] ?? 0),
                'amount_paid' => $this->money($paidByMonth[$month] ?? 0),
            ];
        })->all();
    }

    public function paymentsList(array $params): array
    {
        $sortBy = $this->sortBy($params, ['order_date', 'total_amount', 'outstanding'], 'order_date');
        $sortDir = $this->sortDir($params);

        $base = DB::query()->fromSub($this->buildPayablesPoBaseQuery($params), 'p')
            ->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->select(
                'p.id as po_id',
                'p.po_number',
                'p.supplier_id',
                's.name as supplier_name',
                'p.order_date',
                'p.status',
                'p.total_amount',
                'p.total_paid',
                'p.payment_count',
                'p.last_payment_date',
                'p.notes'
            )
            ->selectRaw('(p.total_amount - p.total_paid) AS outstanding');

        $search = trim((string) ($params['search'] ?? ''));
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('p.po_number', 'like', '%' . $search . '%')
                    ->orWhere('s.name', 'like', '%' . $search . '%');
            });
        }

        $base->orderBy($sortBy, $sortDir);

        return $this->paginateAndFormat($base, function ($row) {
            return [
                'po_id' => (int) $row->po_id,
                'po_number' => $row->po_number,
                'supplier_id' => (int) $row->supplier_id,
                'supplier_name' => $row->supplier_name,
                'order_date' => $this->dateOnly($row->order_date),
                'status' => $row->status,
                'total_amount' => $this->money($row->total_amount),
                'total_paid' => $this->money($row->total_paid),
                'outstanding' => $this->money($row->outstanding),
                'payment_count' => (int) $row->payment_count,
                'last_payment_date' => $this->dateOnly($row->last_payment_date),
                'notes' => $row->notes,
            ];
        }, $params);
    }

    public function filtersWarehouses(): array
    {
        return DB::table('warehouses')
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'code' => $row->code,
                ];
            })->values()->all();
    }

    public function filtersSuppliers(): array
    {
        return DB::table('suppliers')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                ];
            })->values()->all();
    }

    public function filtersCategories(): array
    {
        return DB::table('stock_materials')
            ->where('active', 1)
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values()
            ->all();
    }

    public function filtersBatches(): array
    {
        return DB::table('batches')
            ->where('active', 1)
            ->orderByDesc('id')
            ->get(['id', 'batch_no', 'model_id'])
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'batch_no' => $row->batch_no,
                    'model_id' => (int) $row->model_id,
                ];
            })->values()->all();
    }

    public function filtersUsers(): array
    {
        return DB::table('users')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                ];
            })->values()->all();
    }

    private function buildProcurementPoQuery(array $params, bool $applyCategoryFilter): Builder
    {
        [$dateFrom, $dateTo] = $this->resolveRange($params, 'date_from', 'date_to', 'month');

        $query = DB::table('purchase_orders as po')
            ->whereBetween('po.order_date', [$dateFrom, $dateTo]);

        $supplierIds = $this->csvInts($params['supplier_ids'] ?? null, 'supplier_ids');
        if (!empty($supplierIds)) {
            $query->whereIn('po.supplier_id', $supplierIds);
        }

        $statuses = $this->csvStrings($params['status'] ?? null, 'status');
        if (!empty($statuses)) {
            $statuses = $this->validateEnumArray($statuses, self::PO_STATUSES, 'status', true);
            $query->whereIn('po.status', $statuses);
        }

        if (!empty($params['created_by'])) {
            $query->where('po.created_by', (int) $params['created_by']);
        }

        if (isset($params['min_amount']) && $params['min_amount'] !== '') {
            $query->where('po.total_amount', '>=', (float) $params['min_amount']);
        }

        if ($applyCategoryFilter) {
            $categories = $this->resolveCategories($params['category'] ?? null);
            if (!empty($categories)) {
                $query->whereExists(function ($sub) use ($categories) {
                    $sub->selectRaw('1')
                        ->from('purchase_order_items as poi')
                        ->join('stock_materials as sm', function ($join) {
                            $join->on('sm.id', '=', 'poi.material_id')->where('sm.active', '=', 1);
                        })
                        ->whereColumn('poi.purchase_order_id', 'po.id')
                        ->whereIn('sm.category', $categories);
                });
            }
        }

        return $query;
    }

    private function buildInventoryAggregateQuery(array $params): Builder
    {
        [$dateFrom, $dateTo] = $this->resolveRange($params, 'date_from', 'date_to', 'month');

        $activeOnly = !array_key_exists('active_only', $params) || $this->toBool($params['active_only']);

        $query = DB::table('grn_details as gd')
            ->join('grns as g', 'g.id', '=', 'gd.grn_id')
            ->join('stock_materials as sm', function ($join) {
                $join->on('sm.id', '=', 'gd.stock_item_id')->where('sm.active', '=', 1);
            })
            ->join('warehouses as w', 'w.id', '=', 'g.warehouse_id')
            ->whereBetween('g.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->select(
                'gd.stock_item_id',
                'sm.name',
                'sm.code',
                'sm.category',
                'sm.min_qty',
                'g.warehouse_id',
                'w.name as warehouse_name'
            )
            ->selectRaw('COALESCE(SUM(gd.qty), 0) AS total_qty_received')
            ->selectRaw('COALESCE(SUM(gd.available_qty), 0) AS available_qty')
            ->selectRaw('COALESCE(SUM(gd.available_qty * gd.grn_price), 0) AS stock_value')
            ->selectRaw('MAX(g.created_at) AS last_received_date')
            ->selectRaw('(
                SELECT gd2.grn_price
                FROM grn_details gd2
                WHERE gd2.stock_item_id = gd.stock_item_id
                  AND gd2.active = 1
                ORDER BY gd2.created_at DESC, gd2.id DESC
                LIMIT 1
            ) AS latest_grn_price')
            ->groupBy('gd.stock_item_id', 'sm.name', 'sm.code', 'sm.category', 'sm.min_qty', 'g.warehouse_id', 'w.name');

        if ($activeOnly) {
            $query->where('gd.active', 1);
        }

        $warehouseIds = $this->csvInts($params['warehouse_ids'] ?? null, 'warehouse_ids');
        if (!empty($warehouseIds)) {
            $query->whereIn('g.warehouse_id', $warehouseIds);
        }

        $categories = $this->resolveCategories($params['category'] ?? null);
        if (!empty($categories)) {
            $query->whereIn('sm.category', $categories);
        }

        return $query;
    }

    private function buildMrnBaseQuery(array $params): Builder
    {
        [$dateFrom, $dateTo] = $this->resolveRange($params, 'date_from', 'date_to', 'month');

        $query = DB::table('mrn_details as md')
            ->join('mrns as m', 'm.id', '=', 'md.mrn_id')
            ->join('batches as b', 'b.id', '=', 'm.batch_id')
            ->join('models as mo', 'mo.id', '=', 'b.model_id')
            ->where('md.active', 1)
            ->whereBetween('m.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        $batchIds = $this->csvInts($params['batch_ids'] ?? null, 'batch_ids');
        if (!empty($batchIds)) {
            $query->whereIn('m.batch_id', $batchIds);
        }

        $statuses = $this->csvStrings($params['status'] ?? null, 'status');
        if (!empty($statuses)) {
            $statuses = $this->validateEnumArray($statuses, self::MRN_STATUSES, 'status', false);
            $query->whereIn('m.status', $statuses);
        }

        if (!empty($params['issued_to'])) {
            $query->where('m.issued_to', (int) $params['issued_to']);
        }

        $warehouseIds = $this->csvInts($params['warehouse_ids'] ?? null, 'warehouse_ids');
        if (!empty($warehouseIds)) {
            $query->whereIn('m.warehouse_id', $warehouseIds);
        }

        if (!empty($params['model_id'])) {
            $query->where('b.model_id', (int) $params['model_id']);
        }

        return $query;
    }

    private function buildGrnBaseQuery(array $params): Builder
    {
        [$dateFrom, $dateTo] = $this->resolveRange($params, 'date_from', 'date_to', 'month');

        $query = DB::table('grns as g')
            ->whereBetween('g.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        $warehouseIds = $this->csvInts($params['warehouse_ids'] ?? null, 'warehouse_ids');
        if (!empty($warehouseIds)) {
            $query->whereIn('g.warehouse_id', $warehouseIds);
        }

        $statuses = $this->csvStrings($params['status'] ?? null, 'status');
        if (!empty($statuses)) {
            $statuses = $this->validateEnumArray($statuses, self::GRN_STATUSES, 'status', false);
            $query->whereIn('g.status', $statuses);
        }

        $supplierIds = $this->csvInts($params['supplier_ids'] ?? null, 'supplier_ids');
        if (!empty($supplierIds)) {
            $query->whereExists(function ($sub) use ($supplierIds) {
                $sub->selectRaw('1')
                    ->from('purchase_orders as po')
                    ->whereColumn('po.po_number', 'g.rmpono')
                    ->whereIn('po.supplier_id', $supplierIds);
            });
        }

        if ($this->toBool($params['price_variance_only'] ?? null)) {
            $query->whereExists(function ($sub) use ($params) {
                $sub->fromSub($this->buildGrnVarianceQuery($params), 'v')
                    ->selectRaw('1')
                    ->whereColumn('v.grn_id', 'g.id');
            });
        }

        return $query;
    }

    private function buildGrnLinesQuery(array $params): Builder
    {
        $base = $this->buildGrnBaseQuery($params);

        return $base
            ->join('grn_details as gd', function ($join) {
                $join->on('gd.grn_id', '=', 'g.id')->where('gd.active', '=', 1);
            });
    }

    private function buildGrnVarianceQuery(array $params): Builder
    {
        [$dateFrom, $dateTo] = $this->resolveRange($params, 'date_from', 'date_to', 'month');

        $query = DB::table('grn_details as gd')
            ->join('grns as g', 'g.id', '=', 'gd.grn_id')
            ->leftJoin('stock_materials as sm', 'sm.id', '=', 'gd.stock_item_id')
            ->leftJoin('purchase_orders as po', 'po.po_number', '=', 'g.rmpono')
            ->leftJoin(
                DB::raw('(SELECT purchase_order_id, material_id, MAX(unit_price) AS po_unit_price FROM purchase_order_items GROUP BY purchase_order_id, material_id) poi'),
                function ($join) {
                    $join->on('poi.purchase_order_id', '=', 'po.id')
                        ->on('poi.material_id', '=', 'gd.stock_item_id');
                }
            )
            ->where('gd.active', 1)
            ->whereBetween('g.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->whereNotNull('poi.po_unit_price')
            ->whereRaw('gd.grn_price <> poi.po_unit_price')
            ->select(
                'gd.id as grn_detail_id',
                'g.id as grn_id',
                'g.rmpono',
                'gd.stock_item_id',
                'sm.name as material_name',
                'sm.code as material_code',
                'poi.po_unit_price',
                'gd.grn_price',
                'gd.qty',
                'g.created_at as received_date',
                'po.supplier_id'
            )
            ->selectRaw('(gd.grn_price - poi.po_unit_price) AS variance');

        $supplierIds = $this->csvInts($params['supplier_ids'] ?? null, 'supplier_ids');
        if (!empty($supplierIds)) {
            $query->whereIn('po.supplier_id', $supplierIds);
        }

        $warehouseIds = $this->csvInts($params['warehouse_ids'] ?? null, 'warehouse_ids');
        if (!empty($warehouseIds)) {
            $query->whereIn('g.warehouse_id', $warehouseIds);
        }

        return DB::query()->fromSub($query, 'v');
    }

    private function buildPayablesPoBaseQuery(array $params): Builder
    {
        [$dateFrom, $dateTo] = $this->resolveRange($params, 'date_from', 'date_to', 'month');

        $query = DB::table('purchase_orders as po')
            ->whereBetween('po.order_date', [$dateFrom, $dateTo]);

        $supplierIds = $this->csvInts($params['supplier_ids'] ?? null, 'supplier_ids');
        if (!empty($supplierIds)) {
            $query->whereIn('po.supplier_id', $supplierIds);
        }

        $statuses = $this->csvStrings($params['po_status'] ?? null, 'po_status');
        if (!empty($statuses)) {
            $statuses = $this->validateEnumArray($statuses, self::PO_STATUSES, 'po_status', true);
            $query->whereIn('po.status', $statuses);
        }

        $paymentFrom = $params['payment_date_from'] ?? null;
        $paymentTo = $params['payment_date_to'] ?? null;
        if ($paymentFrom || $paymentTo) {
            [$paymentFrom, $paymentTo] = $this->resolveRange([
                'payment_date_from' => $paymentFrom,
                'payment_date_to' => $paymentTo,
            ], 'payment_date_from', 'payment_date_to', 'month');
        }

        $query->leftJoin(
            DB::raw('(
                SELECT
                    pop.purchase_order_id,
                    SUM(pop.amount) AS total_paid,
                    COUNT(*) AS payment_count,
                    MAX(pop.payment_date) AS last_payment_date
                FROM purchase_order_payments pop
                WHERE pop.active = 1
                ' . ($paymentFrom && $paymentTo ? " AND pop.payment_date BETWEEN '{$paymentFrom}' AND '{$paymentTo}'" : '') . '
                GROUP BY pop.purchase_order_id
            ) pay'),
            'pay.purchase_order_id',
            '=',
            'po.id'
        );

        $base = DB::query()->fromSub(
            $query->select(
                'po.id',
                'po.po_number',
                'po.supplier_id',
                'po.order_date',
                'po.status',
                'po.total_amount',
                'po.notes',
                DB::raw('COALESCE(pay.total_paid, 0) as total_paid'),
                DB::raw('COALESCE(pay.payment_count, 0) as payment_count'),
                'pay.last_payment_date'
            ),
            'x'
        );

        if ($this->toBool($params['unpaid_only'] ?? null)) {
            $base->where('x.payment_count', 0);
        }

        if (isset($params['min_outstanding']) && $params['min_outstanding'] !== '') {
            $base->whereRaw('(x.total_amount - x.total_paid) >= ?', [(float) $params['min_outstanding']]);
        }

        return $base;
    }

    private function paginateAndFormat(Builder $query, callable $mapper, array $params): array
    {
        $page = $this->page($params);
        $perPage = $this->perPage($params);

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())->map($mapper)->values()->all(),
            'meta' => [
                'total' => (int) $paginator->total(),
                'page' => (int) $paginator->currentPage(),
                'per_page' => (int) $paginator->perPage(),
                'last_page' => (int) $paginator->lastPage(),
            ],
        ];
    }

    private function resolveCategories($csv): array
    {
        $categories = $this->csvStrings($csv, 'category');
        if (empty($categories)) {
            return [];
        }

        $normalized = [];
        foreach ($categories as $category) {
            $c = strtolower(trim($category));
            if ($c === 'consumable') {
                $normalized[] = 'consumable';
                $normalized[] = 'consumble';
            } else {
                $normalized[] = $c;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function csvInts($csv, string $field): array
    {
        if ($csv === null || trim((string) $csv) === '') {
            return [];
        }

        $parts = array_filter(array_map('trim', explode(',', (string) $csv)), static function ($value) {
            return $value !== '';
        });

        if (empty($parts)) {
            return [];
        }

        $values = [];
        foreach ($parts as $part) {
            if (!ctype_digit($part)) {
                throw new InvalidArgumentException("{$field} must be a comma-separated list of integers.");
            }
            $values[] = (int) $part;
        }

        return array_values(array_unique($values));
    }

    private function csvStrings($csv, string $field): array
    {
        if ($csv === null || trim((string) $csv) === '') {
            return [];
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', (string) $csv)), static function ($value) {
            return $value !== '';
        }));

        if (empty($parts)) {
            throw new InvalidArgumentException("{$field} has an invalid comma-separated format.");
        }

        return $parts;
    }

    private function validateEnumArray(array $values, array $allowed, string $field, bool $uppercase): array
    {
        $allowedLookup = array_flip($allowed);
        $result = [];

        foreach ($values as $value) {
            $candidate = $uppercase ? strtoupper($value) : strtolower($value);
            if (!isset($allowedLookup[$candidate])) {
                throw new InvalidArgumentException("{$field} contains unsupported value: {$value}");
            }
            $result[] = $candidate;
        }

        return array_values(array_unique($result));
    }

    private function resolveRange(array $params, string $fromKey, string $toKey, string $default): array
    {
        $from = $params[$fromKey] ?? null;
        $to = $params[$toKey] ?? null;

        if (empty($from) && empty($to)) {
            if ($default === 'year') {
                return [
                    Carbon::today()->subMonthsNoOverflow(11)->startOfMonth()->toDateString(),
                    Carbon::today()->toDateString(),
                ];
            }

            return [
                Carbon::today()->startOfMonth()->toDateString(),
                Carbon::today()->toDateString(),
            ];
        }

        if (empty($from) || empty($to)) {
            throw new InvalidArgumentException("Both {$fromKey} and {$toKey} must be provided together.");
        }

        $fromDate = Carbon::createFromFormat('Y-m-d', $from);
        $toDate = Carbon::createFromFormat('Y-m-d', $to);

        if (!$fromDate || !$toDate) {
            throw new InvalidArgumentException("Invalid date format. Expected YYYY-MM-DD.");
        }

        if ($fromDate->gt($toDate)) {
            throw new InvalidArgumentException("{$fromKey} cannot be greater than {$toKey}.");
        }

        return [$fromDate->toDateString(), $toDate->toDateString()];
    }

    private function page(array $params): int
    {
        return max(1, (int) ($params['page'] ?? 1));
    }

    private function perPage(array $params): int
    {
        $perPage = (int) ($params['per_page'] ?? 20);
        if ($perPage < 1) {
            $perPage = 20;
        }

        return min(100, $perPage);
    }

    private function sortBy(array $params, array $allowed, string $default): string
    {
        $sortBy = $params['sort_by'] ?? $default;
        if (!in_array($sortBy, $allowed, true)) {
            return $default;
        }

        return $sortBy;
    }

    private function sortDir(array $params): string
    {
        $dir = strtolower((string) ($params['sort_dir'] ?? 'desc'));
        return $dir === 'asc' ? 'asc' : 'desc';
    }

    private function limit(array $params, int $default, int $max): int
    {
        $limit = (int) ($params['limit'] ?? $default);
        if ($limit < 1) {
            $limit = $default;
        }

        return min($max, $limit);
    }

    private function money($value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    private function dateOnly($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 'true', 'TRUE'], true);
    }
}
