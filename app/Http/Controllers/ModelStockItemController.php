<?php

namespace App\Http\Controllers;

use App\Http\Repositories\ModelStockItemRepository;
use Illuminate\Http\Request;

class ModelStockItemController extends Controller
{
    protected $repository;

    public function __construct(ModelStockItemRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        // If model_id is provided, filter by model
        if ($request->has('model_id')) {
            return response()->json($this->repository->getByModelId($request->model_id));
        }

        return response()->json($this->repository->all());
    }

    public function show($id)
    {
        return response()->json($this->repository->find($id));
    }

    public function store(Request $request)
    {
        $data = $request->only(['stock_item_id', 'model_id', 'consumption']);
        return response()->json($this->repository->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $data = $request->only(['stock_item_id', 'model_id', 'consumption', 'active']);
        return response()->json($this->repository->update($id, $data));
    }

    public function destroy($id)
    {
        $this->repository->delete($id);
        return response()->json(['message' => 'Deleted successfully']);
    }
}
