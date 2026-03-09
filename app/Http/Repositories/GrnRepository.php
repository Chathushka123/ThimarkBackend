<?php

namespace App\Http\Repositories;

use App\Grn;

class GrnRepository
{
    public function all()
    {
        return Grn::with('creator')->get(); // Model global scope already filters active=true
    }

    public function find($id)
    {
        return Grn::with('creator')->findOrFail($id); // Model global scope already filters active=true
    }

    public function create($data = [])
    {
        return Grn::create([
            'rmpono' => $data['rmpono'] ?? null,
            'remark' => $data['remark'] ?? null,
        ]);
    }

    public function delete($id)
    {
        $grn = Grn::findOrFail($id);
        $grn->delete(); // Triggers soft-delete logic (active=false)
        return true;
    }
}
