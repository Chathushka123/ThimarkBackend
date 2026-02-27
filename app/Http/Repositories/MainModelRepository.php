<?php

namespace App\Http\Repositories;

use App\MainModel;

class MainModelRepository
{
    public function all()
    {
        return MainModel::all();
    }

    public function find($id)
    {
        return MainModel::findOrFail($id);
    }

    public function create(array $data)
    {
        return MainModel::create($data);
    }

    public function update($id, array $data)
    {
        $mainModel = MainModel::findOrFail($id);
        $mainModel->update($data);
        return $mainModel;
    }

    public function delete($id)
    {
        $mainModel = MainModel::findOrFail($id);
        $mainModel->delete();
        return true;
    }
}
