<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new \App\Http\Repositories\PurchaseOrderRepository();
    }

    // Get all purchase orders
    public function index()
    {
        return $this->repo->getPurchaseOrders();
    }

    // Get purchase order by id
    public function show($id)
    {
        return $this->repo->getPurchaseOrder($id) ?: response()->json(['message' => 'Purchase order not found'], 404);
    }

    // Create purchase order
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date',
            'status' => 'required|string',
            'subtotal' => 'required|numeric',
            'discount' => 'required|numeric',
            'tax' => 'required|numeric',
            'shipping_cost' => 'required|numeric',
            'total_amount' => 'required|numeric',
            'notes' => 'nullable|string',
        ]);
        try {
            $po = $this->repo->createPurchaseOrder($validated);
            return response()->json($po, 201);
        } catch (\Exception $e) {
            print($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // Update purchase order
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'po_number' => 'sometimes|required|string|max:50|unique:purchase_orders,po_number,' . $id,
            'supplier_id' => 'sometimes|required|exists:suppliers,id',
            'order_date' => 'sometimes|required|date',
            'expected_delivery_date' => 'nullable|date',
            'status' => 'sometimes|required|string',
            'subtotal' => 'sometimes|required|numeric',
            'discount' => 'sometimes|required|numeric',
            'tax' => 'sometimes|required|numeric',
            'shipping_cost' => 'sometimes|required|numeric',
            'total_amount' => 'sometimes|required|numeric',
            'notes' => 'nullable|string',
        ]);
        try {
            $po = $this->repo->updatePurchaseOrder($validated, $id);
            if (!$po) {
                return response()->json(['message' => 'Purchase order not found'], 404);
            }
            return response()->json($po);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
