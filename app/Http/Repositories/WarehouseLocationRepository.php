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
        $location = WarehouseLocation::findOrFail($id);
        $location->update($data);
        return $location->load(['warehouse', 'whlItems']);
    }

    public function delete($id)
    {
        $location = WarehouseLocation::findOrFail($id);
        $location->delete();
        return true;
    }

    public function getByWarehouseId($warehouseId)
    {
        return WarehouseLocation::with(['whlItems'])
            ->where('warehouse_id', $warehouseId)
            ->get();
    }
}
