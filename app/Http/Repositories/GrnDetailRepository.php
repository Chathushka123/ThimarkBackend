<?php

namespace App\Http\Repositories;

use App\GrnDetail;

class GrnDetailRepository
{
    public function all()
    {
        return GrnDetail::with('whlItem')->get(); // Model global scope already filters active=true
    }

    public function find($id)
    {
        return GrnDetail::with('whlItem')->findOrFail($id); // Model global scope already filters active=true
    }

    public function create(array $data)
    {
        return GrnDetail::create($data);
    }

    public function update($id, array $data)
    {
        $grnDetail = GrnDetail::findOrFail($id);
        $grnDetail->update($data);
        return $grnDetail;
    }

    public function delete($id)
    {
        $grnDetail = GrnDetail::findOrFail($id);
        $grnDetail->delete(); // Triggers soft-delete logic (active=false)
        return true;
    }
}
