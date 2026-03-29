<?php

namespace App\Http\Controllers;

use App\StockMaterial;
use Illuminate\Http\Request;

class StockMaterialController extends Controller
{
    public function index()
    {
        return StockMaterial::all();
    }

    public function show($id)
    {
        return StockMaterial::findOrFail($id);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50',
            'supplier' => 'nullable|string|max:100',
            'lead_time' => 'nullable|integer',
            'min_qty' => 'nullable|integer',
            'size' => 'nullable|array',
            'unit_price' => 'nullable|numeric',
            'uom_id' => 'required|exists:uoms,id',
            'category' => 'nullable|in:material,consumble,returnable',
        ]);
        if (!isset($validated['size'])) {
            $validated['size'] = ['base_size'];
        }
        return StockMaterial::create($validated);
    }

    public function update(Request $request, $id)
    {
        $stockMaterial = StockMaterial::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'code' => 'sometimes|required|string|max:50',
            'supplier' => 'nullable|string|max:100',
            'lead_time' => 'nullable|integer',
            'min_qty' => 'nullable|integer',
            'size' => 'nullable|array',
            'unit_price' => 'nullable|numeric',
            'uom_id' => 'sometimes|required|exists:uoms,id',
            'category' => 'nullable|in:material,consumable,returnable',
        ]);
        if (!isset($validated['size'])) {
            $validated['size'] = $stockMaterial->size ?? ['base_size'];
        }
        $stockMaterial->update($validated);
        return $stockMaterial;
    }

    public function destroy($id)
    {
        $stockMaterial = StockMaterial::findOrFail($id);
        $stockMaterial->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function search(Request $request)
    {
        $q = $request->query('q', '');
        $wh = $request->query('wh', '');
        return StockMaterial::where('name', 'like', "%{$q}%")
            ->orWhere('code', 'like', "%{$q}%")
            ->pluck('id');
    }
}
