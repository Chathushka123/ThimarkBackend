<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Repositories\PurchaseOrderRepository;


class PurchaseOrderController extends Controller
{
    private $repo;

    // public function __construct()
    // {
    //     $this->repo = new \App\Http\Repositories\PurchaseOrderRepository();
    // }

    public function __construct(PurchaseOrderRepository $repository)
    {
        $this->repo = $repository;
    }

    // Get all purchase orders
    public function index()
    {
        return $this->repo->getPurchaseOrders();
    }

    // Get APPROVED and SENT purchase orders
    public function approvedAndSent()
    {
        return response()->json($this->repo->getApprovedAndSentOrders());
    }

    // Get purchase order by id
    public function show($id)
    {
        return $this->repo->getPurchaseOrder($id) ?: response()->json(['message' => 'Purchase order not found'], 404);
    }

    // Create purchase order
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'po_number'                => 'nullable|string',
                'supplier_id'              => 'required|exists:suppliers,id',
                'order_date'               => 'required|date_format:Y-m-d',
                'expected_delivery_date'   => 'nullable|date_format:Y-m-d',
                'status'                   => 'required|string',
                'subtotal'                 => 'required|numeric',
                'discount'                 => 'required|numeric',
                'tax'                      => 'required|numeric',
                'shipping_cost'            => 'required|numeric',
                'total_amount'             => 'required|numeric',
                'notes'                    => 'nullable|string',
                'items'                    => 'required|array|min:1',
                'items.*.material_id'      => 'required|exists:stock_materials,id',
                'items.*.quantity'         => 'required|numeric|min:0',
                'items.*.unit_price'       => 'required|numeric|min:0',
                'items.*.total'            => 'required|numeric|min:0',
                'items.*.expected_delivery_date' => 'nullable|date_format:Y-m-d',
            ]);
            $po = $this->repo->createPurchaseOrder($validated);
            return response()->json($po, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    // Update purchase order
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'supplier_id'              => 'sometimes|required|exists:suppliers,id',
            'order_date'               => 'sometimes|required|date_format:Y-m-d',
            'expected_delivery_date'   => 'nullable|date_format:Y-m-d',
            'status'                   => 'sometimes|required|string',
            'subtotal'                 => 'sometimes|required|numeric',
            'discount'                 => 'sometimes|required|numeric',
            'tax'                      => 'sometimes|required|numeric',
            'shipping_cost'            => 'sometimes|required|numeric',
            'total_amount'             => 'sometimes|required|numeric',
            'notes'                    => 'nullable|string',
            'items'                    => 'sometimes|array',
            'items.*.id'               => 'nullable|integer',
            'items.*.material_id'      => 'nullable|exists:stock_materials,id',
            'items.*.quantity'         => 'nullable|numeric|min:0',
            'items.*.unit_price'       => 'nullable|numeric|min:0',
            'items.*.total'            => 'nullable|numeric|min:0',
            'items.*.expected_delivery_date' => 'nullable|date_format:Y-m-d',
            'items.*._rowstate'        => 'required_with:items|string|in:NEW,UPDATED,DELETED,MODIFIED,POPULATED',
        ]);

        $validator->after(function ($v) use ($request) {
            foreach ($request->input('items', []) as $index => $item) {
                if (($item['_rowstate'] ?? '') !== 'DELETED') {
                    if (empty($item['material_id'])) {
                        $v->errors()->add("items.$index.material_id", 'The material id field is required.');
                    }
                    if (!isset($item['quantity']) || $item['quantity'] === '') {
                        $v->errors()->add("items.$index.quantity", 'The quantity field is required.');
                    }
                    if (!isset($item['unit_price']) || $item['unit_price'] === '') {
                        $v->errors()->add("items.$index.unit_price", 'The unit price field is required.');
                    }
                    if (!isset($item['total']) || $item['total'] === '') {
                        $v->errors()->add("items.$index.total", 'The total field is required.');
                    }
                }
            }
        });

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

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
