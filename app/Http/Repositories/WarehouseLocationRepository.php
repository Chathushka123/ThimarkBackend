<?php

namespace App\Http\Repositories;

use App\WarehouseLocation;

class WarehouseLocationRepository
{
    public function all()
    {
        return WarehouseLocation::with(['warehouse', 'whlItems'])->get();
    }

    public function find($id)
    {
        return WarehouseLocation::with(['warehouse', 'whlItems'])->findOrFail($id);
    }

    public function create(array $data)
    {
        return WarehouseLocation::create($data);
    }

    public function update($id, array $data)
    {
        $location = WarehouseLocation::with('whlItems')->findOrFail($id);

        if (isset($data['active']) && $data['active'] == 0) {
            $hasStock = $location->whlItems->contains(fn($item) => $item->qty > 0);
            if ($hasStock) {
                throw new \Exception('Cannot deactivate location: it has items with stock quantity greater than zero.');
            }
        }

        $location->update($data);
        return $location->load(['warehouse', 'whlItems']);
    }

    public function delete($id)
    {
        $location = WarehouseLocation::with('whlItems')->findOrFail($id);

        $hasStock = $location->whlItems->contains(fn($item) => $item->qty > 0);
        if ($hasStock) {
            throw new \Exception('Cannot deactivate location: it has items with stock quantity greater than zero.');
        }

        if ($location->active == 1) {
            $location->active = 0;
            $location->save();
        }
        return true;
    }

    public function getByWarehouseId($warehouseId)
    {
        return WarehouseLocation::with(['whlItems'])
            ->where('warehouse_id', $warehouseId)
            ->get();
    }
}
