<?php

namespace App\Http\Controllers;

use App\Http\Repositories\WarehouseRepository;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    protected $repository;

    public function __construct(WarehouseRepository $repository)
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
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50',
            'location_basis' => 'required|int',
            'locations' => 'array',
            'locations.*.rack' => 'nullable|string|max:50',
            'locations.*.bin' => 'nullable|string|max:50',
        ]);

        return response()->json($this->repository->create($validated), 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'code' => 'sometimes|required|string|max:50',
            'location_basis' => 'sometimes|required|int',
            'locations' => 'array',
            'locations.*.id' => 'sometimes|integer|exists:warehouse_locations,id',
            'locations.*.rack' => 'nullable|string|max:50',
            'locations.*.bin' => 'nullable|string|max:50',
        ]);

        return response()->json($this->repository->update($id, $validated));
    }

    public function destroy($id)
    {
        $this->repository->delete($id);
        return response()->json(['message' => 'Deleted successfully']);
    }
}
