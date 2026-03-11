<?php

namespace App\Http\Repositories;

use App\StockMaterial;

class StockMaterialRepository
{
    public function all()
    {
        return StockMaterial::all(); // Model global scope already filters active=true
    }

    public function find($id)
    {
        return StockMaterial::findOrFail($id); // Model global scope already filters active=true
    }

    public function create(array $data)
    {
        $data['active'] = 1;
        return StockMaterial::create($data);
    }

    public function update($id, array $data)
    {
        $stockMaterial = StockMaterial::findOrFail($id);
        $stockMaterial->update($data);
        return $stockMaterial;
    }

    public function delete($id)
    {
        $stockMaterial = StockMaterial::findOrFail($id);
        $stockMaterial->active = 0;
        $stockMaterial->save();
        return true;
    }

    public function search(string $q, ?string $wh = null): \Illuminate\Support\Collection
    {
        $query = StockMaterial::query()
            ->where(function ($query) use ($q) {
                $query->where('stock_materials.name', 'like', "%{$q}%")
                      ->orWhere('stock_materials.code', 'like', "%{$q}%");
            });

        if ($wh) {
            $query->join('whl_items', 'whl_items.stock_item_id', '=', 'stock_materials.id')
                  ->join('warehouse_locations', 'warehouse_locations.id', '=', 'whl_items.whl_id')
                  ->join('warehouses', 'warehouses.id', '=', 'warehouse_locations.warehouse_id')
                  ->where('warehouses.id', $wh)
                  ->select('stock_materials.*')
                  ->distinct();
        }

        return $query->pluck('stock_materials.id');
    }
}
