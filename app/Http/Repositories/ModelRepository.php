<?php

namespace App\Http\Repositories;

use App\Model;

class ModelRepository
{
    public function all()
    {
        return Model::with([
            'mainModel',
            'modelStockItems' => function ($query) {
                $query->where('active', true);
            }
        ])->where('active', true)->get();
    }

    public function find($id)
    {
        return Model::with([
            'mainModel',
            'modelStockItems' => function ($query) {
                $query->where('active', true);
            }
        ])->findOrFail($id);
    }

    public function create(array $data)
    {
        $modelStockItems = $data['model_stock_items'] ?? [];
        unset($data['model_stock_items']);
        $model = Model::create($data);
        foreach ($modelStockItems as $itemData) {
            $model->modelStockItems()->create($itemData);
        }
        return $model->load([
            'mainModel',
            'modelStockItems' => function ($query) {
                $query->where('active', true);
            }
        ]);
    }

    public function update($id, array $data)
    {
        $model = Model::findOrFail($id);
        $model->update($data);
        // Optionally update modelStockItems if provided
        if (isset($data['model_stock_items'])) {
            foreach ($data['model_stock_items'] as $itemData) {
                if (isset($itemData['id'])) {
                    $item = $model->modelStockItems()->find($itemData['id']);
                    if ($item) {
                        $item->update($itemData);
                    }
                } else {
                    $model->modelStockItems()->create($itemData);
                }
            }
        }
        return $model->load([
            'mainModel',
            'modelStockItems' => function ($query) {
                $query->where('active', true);
            }
        ]);
    }

    public function delete($id)
    {
        $model = Model::findOrFail($id);
        $model->delete();
        return true;
    }
}
