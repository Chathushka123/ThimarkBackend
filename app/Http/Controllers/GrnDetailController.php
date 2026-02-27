<?php

namespace App\Http\Controllers;

use App\GrnDetail;
use Illuminate\Http\Request;

class GrnDetailController extends Controller
{
    public function index()
    {
        return GrnDetail::with('whlItem')->get();
    }

    public function show($id)
    {
        return GrnDetail::with('whlItem')->findOrFail($id);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'whl_item_id' => 'required|exists:whl_items,id',
            'qty' => 'required|integer',
            'grn_price' => 'required|numeric',
        ]);
        return GrnDetail::create($validated);
    }

    public function update(Request $request, $id)
    {
        $grnDetail = GrnDetail::findOrFail($id);
        $validated = $request->validate([
            'whl_item_id' => 'sometimes|required|exists:whl_items,id',
            'qty' => 'sometimes|required|integer',
            'grn_price' => 'sometimes|required|numeric',
        ]);
        $grnDetail->update($validated);
        return $grnDetail;
    }

    public function destroy($id)
    {
        $grnDetail = GrnDetail::findOrFail($id);
        $grnDetail->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
