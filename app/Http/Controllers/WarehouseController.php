<?php

namespace App\Http\Controllers;

use App\Warehouse;
use App\WarehouseLocation;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index()
    {
        return Warehouse::with('locations')->get();
    }

    public function show($id)
    {
        return Warehouse::with('locations')->findOrFail($id);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50',
            'location_basis' => 'required|boolean',
            'locations' => 'array',
            'locations.*.rack' => 'nullable|string|max:50',
            'locations.*.bin' => 'nullable|string|max:50',
        ]);

        $warehouse = Warehouse::create($validated);
        if (!empty($validated['locations'])) {
            foreach ($validated['locations'] as $loc) {
                $warehouse->locations()->create($loc);
            }
        }
        return $warehouse->load('locations');
    }

    public function update(Request $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'code' => 'sometimes|required|string|max:50',
            'location_basis' => 'sometimes|required|boolean',
            'locations' => 'array',
            'locations.*.id' => 'sometimes|integer|exists:warehouse_locations,id',
            'locations.*.rack' => 'nullable|string|max:50',
            'locations.*.bin' => 'nullable|string|max:50',
        ]);
        $warehouse->update($validated);
        if (isset($validated['locations'])) {
            foreach ($validated['locations'] as $loc) {
                if (isset($loc['id'])) {
                    $location = $warehouse->locations()->find($loc['id']);
                    if ($location) {
                        $location->update($loc);
                    }
                } else {
                    $warehouse->locations()->create($loc);
                }
            }
        }
        return $warehouse->load('locations');
    }

    public function destroy($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->locations()->delete();
        $warehouse->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
