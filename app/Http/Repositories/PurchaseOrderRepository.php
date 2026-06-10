<?php

namespace App\Http\Repositories;

use App\PurchaseOrder;
use App\PurchaseOrderPayment;
use Illuminate\Support\Facades\DB;
use Exception;

class PurchaseOrderRepository
{
    public function getActiveSummary(): array
    {
        $statuses = ['APPROVED', 'SENT', 'RECEIVED'];

        $allPos = PurchaseOrder::with(['supplier', 'items.material', 'payments'])
            ->whereIn('status', $statuses)
            ->get();

        return $this->buildSummaryByStatus($allPos, $statuses);
    }

    public function getFilteredSummary(array $filters): array
    {
        $statuses  = $filters['statuses'];
        $dateField = $filters['date_field'];  // created_at | order_date | expected_delivery_date
        $from      = $filters['from'] ?? null;
        $to        = $filters['to'] ?? null;

        $query = PurchaseOrder::with(['supplier', 'items.material', 'payments'])
            ->whereIn('status', $statuses);

        if ($from) {
            $query->whereDate($dateField, '>=', $from);
        }
        if ($to) {
            $query->whereDate($dateField, '<=', $to);
        }

        $allPos = $query->get();

        return $this->buildSummaryByStatus($allPos, $statuses);
    }

    private function buildSummaryByStatus($allPos, array $statuses): array
    {
        // Batch-load GRN details for all POs in a single query
        $poIds = $allPos->pluck('id')->all();

        $grnByPo = collect([]);
        if (!empty($poIds)) {
            $grnByPo = DB::table('grns')
                ->join('grn_details', 'grn_details.grn_id', '=', 'grns.id')
                ->join('stock_materials', 'stock_materials.id', '=', 'grn_details.stock_item_id')
                ->whereIn('grns.rmpono', $poIds)
                ->where('grns.active', 1)
                ->where('grn_details.active', 1)
                ->select(
                    'grns.rmpono as po_id',
                    'grns.id as grn_id',
                    'grn_details.stock_item_id',
                    'stock_materials.name as material_name',
                    'grn_details.qty',
                    'grn_details.available_qty',
                    'grn_details.grn_price',
                    DB::raw('grn_details.qty * grn_details.grn_price as grn_value')
                )
                ->orderBy('grns.id')
                ->orderBy('grn_details.id')
                ->get()
                ->groupBy('po_id');
        }

        $result = [];

        foreach ($statuses as $status) {
            $statusPos = $allPos->where('status', $status)->values();

            $poDetails = $statusPos->map(function ($po) use ($grnByPo) {
                $paidAmount = $po->payments->sum('amount');
                $grnItems   = $grnByPo->get($po->id, collect([]));

                return [
                    'id'                     => $po->id,
                    'po_number'              => $po->po_number,
                    'supplier_name'          => $po->supplier->name ?? null,
                    'order_date'             => $po->order_date,
                    'expected_delivery_date' => $po->expected_delivery_date,
                    'payment_date'           => $po->payment_date,
                    'in_house_date'          => $po->in_house_date,
                    'subtotal'               => (float) $po->subtotal,
                    'discount'               => (float) $po->discount,
                    'tax'                    => (float) $po->tax,
                    'shipping_cost'          => (float) $po->shipping_cost,
                    'notes'                  => $po->notes,
                    'status'                 => $po->status,
                    'created_at'             => $po->created_at,
                    'created_by'             => $po->created_by,
                    'po_qty'                 => [
                        'qty'       => (float) $po->items->sum('quantity'),
                        'breakdown' => $po->items->map(fn($item) => [
                            'material_name' => $item->material->name ?? null,
                            'qty'           => (float) $item->quantity,
                        ])->values(),
                    ],
                    'total_amount' => [
                        'amount'    => (float) $po->items->sum('total'),
                        'breakdown' => $po->items->map(fn($item) => [
                            'material_name' => $item->material->name ?? null,
                            'unit_price'    => (float) $item->unit_price,
                            'total'         => (float) $item->total,
                        ])->values(),
                    ],
                    'paid_amount' => [
                        'amount'    => (float) $paidAmount,
                        'breakdown' => $po->payments->values(),
                    ],
                    'balance' => [
                        'amount' => ((float) $po->items->sum('total')) - (float) $paidAmount,
                    ],
                    'grn_qty' => [
                        'qty'           => (float) $grnItems->sum('qty'),
                        'available_qty' => (float) $grnItems->sum('available_qty'),
                        'grn_value'     => (float) $grnItems->sum('grn_value'),
                        'grn_count'     => $grnItems->pluck('grn_id')->unique()->count(),
                        'breakdown'     => $grnItems->map(fn($g) => [
                            'grn_id'        => $g->grn_id,
                            'material_name' => $g->material_name,
                            'qty'           => (float) $g->qty,
                            'available_qty' => (float) $g->available_qty,
                            'grn_price'     => (float) $g->grn_price,
                            'grn_value'     => (float) $g->grn_value,
                        ])->values(),
                    ],
                ];
            })->values();

            $result[$status] = [
                'po_details'                    => $poDetails,
                'total_qty_all_pos'             => $poDetails->sum(fn($po) => $po['po_qty']['qty']),
                'total_amount_all_pos'          => $poDetails->sum(fn($po) => $po['total_amount']['amount']),
                'total_balance_all_pos'         => $poDetails->sum(fn($po) => $po['balance']['amount']),
                'total_paid_amount_all_pos'     => $poDetails->sum(fn($po) => $po['paid_amount']['amount']),
                'total_grn_qty_all_pos'           => $poDetails->sum(fn($po) => $po['grn_qty']['qty']),
                'total_grn_available_qty_all_pos' => $poDetails->sum(fn($po) => $po['grn_qty']['available_qty']),
                'total_grn_value_all_pos'         => $poDetails->sum(fn($po) => $po['grn_qty']['grn_value']),
                'no_of_pos'                     => $statusPos->count(),
            ];
        }

        return $result;
    }

    public function getPurchaseOrders()
    {
        return PurchaseOrder::with(['supplier', 'items'])->get();
    }

    public function getApprovedAndSentOrders()
    {
        return PurchaseOrder::select('id', 'po_number')
            // ->whereIn('status', ['APPROVED', 'SENT'])
            ->orderByDesc('id')
            ->get();
    }

    public function getPurchaseOrderDetails($id)
    {
        $po = PurchaseOrder::with(['supplier', 'items.material'])->find($id);

        return $this->appendGrnAndBalanceQty($po);
    }

    public function getPurchaseOrder($id)
    {
        $po = PurchaseOrder::with(['supplier', 'items'])->find($id);

        return $this->appendGrnAndBalanceQty($po);
    }

    private function appendGrnAndBalanceQty($po)
    {
        if (!$po) {
            return null;
        }

        $grnQtyByMaterial = DB::table('grns')
            ->join('grn_details', 'grn_details.grn_id', '=', 'grns.id')
            ->where('grns.rmpono', $po->id)
            ->where('grns.active', 1)
            ->where('grn_details.active', 1)
            ->groupBy('grn_details.stock_item_id')
            ->selectRaw('grn_details.stock_item_id as material_id, SUM(grn_details.qty) as grn_qty')
            ->pluck('grn_qty', 'material_id');

        $po->items->transform(function ($item) use ($grnQtyByMaterial) {
            $grnQty = (float) ($grnQtyByMaterial[$item->material_id] ?? 0);
            $item->grn_qty = $grnQty;
            $item->balance_qty = (float) $item->quantity - $grnQty;
            return $item;
        });

        return $po;
    }

    public function createPurchaseOrder($data)
    {
        $nextId = (DB::table('purchase_orders')->max('id') ?? 0) + 1;
        $po_num = sprintf('PO-%s-%06d', date('Y'), $nextId);

        if (!empty($data['po_number'])) {
            if (PurchaseOrder::where('po_number', $data['po_number'])->exists()) {
                throw new \InvalidArgumentException('Entered PO Number already exists!');
            }
            $po_num = $data['po_number'];
        }

        try {
            $po = PurchaseOrder::create([
                'po_number'              => $po_num,
                'supplier_id'            => $data['supplier_id'],
                'order_date'             => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status'                 => strtoupper($data['status']),
                'subtotal'               => $data['subtotal'],
                'discount'               => $data['discount'],
                'tax'                    => $data['tax'],
                'shipping_cost'          => $data['shipping_cost'],
                'total_amount'           => $data['total_amount'],
                'notes'                  => $data['notes'] ?? null,
                'payment_date'           => $data['payment_date'] ?? null,
                'in_house_date'          => $data['in_house_date'] ?? null,
            ]);

            // Create PO items
            foreach ($data['items'] as $item) {
                $po->items()->create([
                    'material_id'            => $item['material_id'],
                    'quantity'               => $item['quantity'],
                    'unit_price'             => $item['unit_price'],
                    'total'                  => $item['total'],

                    'expected_delivery_date' => $item['expected_delivery_date'] ?? null,
                ]);
            }

            return $po->load(['supplier', 'items']);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function updatePurchaseOrder($data, $id)
    {
        $po = PurchaseOrder::find($id);
        if (!$po) {
            return null;
        }

        $po->update(array_filter([
            'supplier_id'            => $data['supplier_id'] ?? null,
            'order_date'             => $data['order_date'] ?? null,
            'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
            'status'                 => $data['status'] ?? null,
            'subtotal'               => $data['subtotal'] ?? null,
            'discount'               => $data['discount'] ?? null,
            'tax'                    => $data['tax'] ?? null,
            'shipping_cost'          => $data['shipping_cost'] ?? null,
            'total_amount'           => $data['total_amount'] ?? null,
            'notes'                  => $data['notes'] ?? null,
            'payment_date'           => $data['payment_date'] ?? null,
            'in_house_date'          => $data['in_house_date'] ?? null,
        ], fn($v) => !is_null($v)));

        // Handle items by _rowstate
        foreach ($data['items'] ?? [] as $item) {
            $rowstate = $item['_rowstate'] ?? null;

            if ($rowstate === 'NEW') {
                $po->items()->create([
                    'material_id'            => $item['material_id'],
                    'quantity'               => $item['quantity'],
                    'unit_price'             => $item['unit_price'],
                    'expected_delivery_date' => $item['expected_delivery_date'] ?? null,
                    'total'                  => $item['total'],
                ]);
            } elseif ($rowstate === 'MODIFIED' && !empty($item['id'])) {
                $po->items()->where('id', $item['id'])->update([
                    'material_id'            => $item['material_id'],
                    'quantity'               => $item['quantity'],
                    'unit_price'             => $item['unit_price'],
                    'expected_delivery_date' => $item['expected_delivery_date'] ?? null,
                    'total'                  => $item['total'],
                ]);
            } elseif ($rowstate === 'DELETED' && !empty($item['id'])) {
                $po->items()->where('id', $item['id'])->delete();
            }
        }

        return $po->load(['supplier', 'items']);
    }

    public function createPaymentTransaction(int $id, array $data)
    {
        $po = PurchaseOrder::find($id);
        if (!$po) {
            throw new \Exception('Purchase order not found');
        }

        return PurchaseOrderPayment::create([
            'purchase_order_id' => $id,
            'amount' => $data['amount'],
            'note' => $data['note'],
            'payment_date' => now()->toDateString(),
        ]);
    }

    public function getPaymentTransactions(int $id)
    {
        $po = PurchaseOrder::find($id);
        if (!$po) {
            return null;
        }

        return PurchaseOrderPayment::where('purchase_order_id', $id)
            ->orderByDesc('id')
            ->get();
    }
}
