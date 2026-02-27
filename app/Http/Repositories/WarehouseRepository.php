<?php

namespace App\Http\Repositories;

use App\Warehouse;

class WarehouseRepository
{
    public function all()
    {
        return Warehouse::with('locations')->get(); // Model global scope already filters active=true
    }

    public function find($id)
    {
        return Warehouse::with('locations')->findOrFail($id); // Model global scope already filters active=true
    }

    public function create(array $data)
    {
        $locations = $data['locations'] ?? [];
        unset($data['locations']);
        $warehouse = Warehouse::create($data);
        foreach ($locations as $loc) {
            $warehouse->locations()->create($loc);
        }
        return $warehouse->load('locations');
    }

    public function update($id, array $data)
    {
        $warehouse = Warehouse::findOrFail($id);
        $locations = $data['locations'] ?? [];
        unset($data['locations']);
        $warehouse->update($data);
        foreach ($locations as $loc) {
            if (isset($loc['id'])) {
                $location = $warehouse->locations()->find($loc['id']);
                if ($location) {
                    $location->update($loc);
                }
            } else {
                $warehouse->locations()->create($loc);
            }
        }
        return $warehouse->load('locations');
    }

    public function delete($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->locations()->delete(); // Triggers soft-delete logic for locations
        $warehouse->delete(); // Triggers soft-delete logic for warehouse
        return true;
    }
}
