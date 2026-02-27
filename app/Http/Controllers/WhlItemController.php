<?php

namespace App\Http\Controllers;

use App\WhlItem;
use Illuminate\Http\Request;

class WhlItemController extends Controller
{
    public function index()
    {
        return WhlItem::with(['warehouseLocation', 'stockItem'])->get();
    }

    public function show($id)
    {
        return WhlItem::with(['warehouseLocation', 'stockItem'])->findOrFail($id);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'whl_id' => 'required|exists:warehouse_locations,id',
            'stock_item_id' => 'required|exists:stock_materials,id',
            'qty' => 'required|integer',
        ]);
        return WhlItem::create($validated);
    }

    public function update(Request $request, $id)
    {
        $whlItem = WhlItem::findOrFail($id);
        $validated = $request->validate([
            'whl_id' => 'sometimes|required|exists:warehouse_locations,id',
            'stock_item_id' => 'sometimes|required|exists:stock_materials,id',
            'qty' => 'sometimes|required|integer',
        ]);
        $whlItem->update($validated);
        return $whlItem;
    }

    public function destroy($id)
    {
        $whlItem = WhlItem::findOrFail($id);
        $whlItem->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
