<?php

namespace App\Http\Repositories;

use App\ModelStockItem;

class ModelStockItemRepository
{
    public function all()
    {
        return ModelStockItem::with(['model', 'stockItem'])->get();
    }

    public function find($id)
    {
        return ModelStockItem::with(['model', 'stockItem'])->findOrFail($id);
    }

    public function create(array $data)
    {
        return ModelStockItem::create($data);
    }

    public function update($id, array $data)
    {
        $modelStockItem = ModelStockItem::findOrFail($id);
        $modelStockItem->update($data);
        return $modelStockItem->load(['model', 'stockItem']);
    }

    public function delete($id)
    {
        $modelStockItem = ModelStockItem::findOrFail($id);
        $modelStockItem->delete();
        return true;
    }

    public function getByModelId($modelId)
    {
        return ModelStockItem::with(['stockItem'])
            ->where('model_id', $modelId)
            ->get();
    }
}
