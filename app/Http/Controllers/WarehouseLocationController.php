<?php

namespace App\Http\Controllers;

use App\Http\Repositories\WarehouseLocationRepository;
use Illuminate\Http\Request;

class WarehouseLocationController extends Controller
{
    protected $repository;

    public function __construct(WarehouseLocationRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        // If warehouse_id is provided, filter by warehouse
        if ($request->has('warehouse_id')) {
            return response()->json($this->repository->getByWarehouseId($request->warehouse_id));
        }

        return response()->json($this->repository->all());
    }

    public function show($id)
    {
        return response()->json($this->repository->find($id));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'rack' => 'nullable|string|max:50',
            'bin' => 'nullable|string|max:50',
        ]);

        return response()->json($this->repository->create($validated), 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            'rack' => 'nullable|string|max:50',
            'bin' => 'nullable|string|max:50',
        ]);

        return response()->json($this->repository->update($id, $validated));
    }

    public function destroy($id)
    {
        $this->repository->delete($id);
        return response()->json(['message' => 'Deleted successfully']);
    }
}
