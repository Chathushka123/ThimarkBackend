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

    public function search(string $q): \Illuminate\Support\Collection
    {
        return StockMaterial::where('name', 'like', "%{$q}%")
            ->orWhere('code', 'like', "%{$q}%")
            ->pluck('id');
    }
}
