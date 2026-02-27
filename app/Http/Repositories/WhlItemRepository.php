<?php

namespace App\Http\Repositories;

use App\WhlItem;

class WhlItemRepository
{
    public function all()
    {
        return WhlItem::with(['warehouseLocation', 'stockItem'])->get(); // Model global scope already filters active=true
    }

    public function find($id)
    {
        return WhlItem::with(['warehouseLocation', 'stockItem'])->findOrFail($id); // Model global scope already filters active=true
    }

    public function create(array $data)
    {
        unset($data['grn_price']);
        return WhlItem::create($data);
    }

    public function update($id, array $data)
    {
        $whlItem = WhlItem::findOrFail($id);
        unset($data['grn_price']);
        $whlItem->update($data);
        return $whlItem;
    }

    public function delete($id)
    {
        $whlItem = WhlItem::findOrFail($id);
        $whlItem->delete(); // Triggers soft-delete logic (active=false)
        return true;
    }
}
