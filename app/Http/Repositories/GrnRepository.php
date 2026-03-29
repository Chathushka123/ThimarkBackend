<?php

namespace App\Http\Repositories;

use App\Grn;

class GrnRepository
{
    public function all()
    {
        return Grn::with(['creator', 'warehouse'])->get();
    }

    public function find($id)
    {
        return Grn::with(['creator', 'warehouse'])->findOrFail($id);
    }

    public function create($data = [])
    {
        return Grn::create([
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'status'       => $data['status'] ?? null,
            'rmpono'       => $data['rmpono'] ?? null,
            'remark'       => $data['remark'] ?? null,
        ]);
    }

    public function delete($id)
    {
        $grn = Grn::findOrFail($id);
        $grn->delete(); // Triggers soft-delete logic (active=false)
        return true;
    }
}
