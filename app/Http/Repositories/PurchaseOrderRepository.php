<?php

namespace App\Http\Repositories;

use App\PurchaseOrder;
use Illuminate\Support\Facades\Validator;
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
            // Create PO without po_number first
            $po = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => $data['status'],
                'subtotal' => $data['subtotal'],
                'discount' => $data['discount'],
                'tax' => $data['tax'],
                'shipping_cost' => $data['shipping_cost'],
                'total_amount' => $data['total_amount'],
                'notes' => $data['notes'] ?? null,
            ]);

            // Generate po_number: PO-{year}-{supplier_id}-{id}
            $year = date('Y');
            $supplierId = $po->supplier_id;
            $id = $po->id;
            $poNumber = sprintf('PO-%s-%s-%06d', $year, $supplierId, $id);
            $po->po_number = $poNumber;
            $po->save();

            return $po;
        } catch (\Exception $e) {
            return @e;
        }
    }

    public function updatePurchaseOrder($data, $id)
    {
        $po = PurchaseOrder::find($id);
        if (!$po) {
            return null;
        }
        $po->update($data);
        return $po;
    }
}
