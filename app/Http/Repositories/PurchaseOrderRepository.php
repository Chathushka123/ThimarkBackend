<?php

namespace App\Http\Repositories;

use App\PurchaseOrder;
use App\PurchaseOrderPayment;
use Illuminate\Support\Facades\DB;
use Exception;

class PurchaseOrderRepository
{
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
