<?php

namespace App\Http\Repositories;

use App\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Exception;

class PurchaseOrderRepository
{
    public function getPurchaseOrders()
    {
        return PurchaseOrder::with(['supplier', 'items'])->get();
    }

    public function getPurchaseOrder($id)
    {
        return PurchaseOrder::with(['supplier', 'items'])->find($id);
    }

    public function createPurchaseOrder($data)
    {
        try {
            $nextId = (DB::table('purchase_orders')->max('id') ?? 0) + 1;
            $po_num = sprintf('PO-%s-%06d', date('Y'), $nextId);

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
                    'expected_delivery_date' => $item['expected_delivery_date'] ?? null,
                ]);
            }

            return $po->load(['supplier', 'items']);
        } catch (\Exception $e) {
            // Return error details for debugging
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
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
                ]);
            } elseif ($rowstate === 'MODIFIED' && !empty($item['id'])) {
                $po->items()->where('id', $item['id'])->update([
                    'material_id'            => $item['material_id'],
                    'quantity'               => $item['quantity'],
                    'unit_price'             => $item['unit_price'],
                    'expected_delivery_date' => $item['expected_delivery_date'] ?? null,
                ]);
            } elseif ($rowstate === 'DELETED' && !empty($item['id'])) {
                $po->items()->where('id', $item['id'])->delete();
            }
        }

        return $po->load(['supplier', 'items']);
    }
}
