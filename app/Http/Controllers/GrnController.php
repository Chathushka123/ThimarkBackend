<?php

namespace App\Http\Controllers;

use App\Grn;
use Illuminate\Http\Request;

class GrnController extends Controller
{
    public function index()
    {
        return Grn::with('creator')->get();
    }

    public function show($id)
    {
        return Grn::with('creator')->findOrFail($id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'rmpono' => 'nullable|string|max:255',
            'remark' => 'nullable|string',
        ]);

        $grn = Grn::create([
            'rmpono' => $request->input('rmpono'),
            'remark' => $request->input('remark'),
        ]);

        return $grn->load('creator');
    }

    public function destroy($id)
    {
        $grn = Grn::findOrFail($id);
        $grn->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
