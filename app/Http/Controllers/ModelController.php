<?php

namespace App\Http\Controllers;

use App\Http\Repositories\ModelRepository;
use Illuminate\Http\Request;

class ModelController extends Controller
{
    protected $repository;

    public function __construct(ModelRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index()
    {
        return response()->json($this->repository->all());
    }

    public function show($id)
    {
        return response()->json($this->repository->find($id));
    }

    public function store(Request $request)
    {
        $data = $request->only(['main_model_id', 'color', 'sizes', 'name', 'active', 'model_stock_items']);
        return response()->json($this->repository->create($data));
    }

    public function update(Request $request, $id)
    {
        $data = $request->only(['main_model_id', 'color', 'sizes', 'name', 'active', 'model_stock_items']);
        return response()->json($this->repository->update($id, $data));
    }
}
