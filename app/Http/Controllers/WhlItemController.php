<?php

namespace App\Http\Controllers;

use App\Http\Repositories\WhlItemRepository;
use Illuminate\Http\Request;

class WhlItemController extends Controller
{
    protected $repository;

    public function __construct(WhlItemRepository $repository)
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
            'whl_id' => 'required|exists:warehouse_locations,id',
            'stock_item_id' => 'required|exists:stock_materials,id',
            'qty' => 'required|numeric|min:0',
        ]);

        return response()->json($this->repository->create($validated), 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'whl_id' => 'sometimes|required|exists:warehouse_locations,id',
            'stock_item_id' => 'sometimes|required|exists:stock_materials,id',
            'qty' => 'sometimes|required|numeric|min:0',
        ]);

        return response()->json($this->repository->update($id, $validated));
    }

    public function destroy($id)
    {
        $this->repository->delete($id);
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function moveBin(Request $request)
    {
        $validated = $request->validate([
            'from_bin_id'  => 'required|exists:warehouse_locations,id',
            'to_bin_id'    => 'required|exists:warehouse_locations,id|different:from_bin_id',
            'material_id'  => 'required|exists:stock_materials,id',
            'qty'          => 'required|numeric|min:0.0001',
        ]);

        $result = $this->repository->moveBin(
            $validated['from_bin_id'],
            $validated['to_bin_id'],
            $validated['material_id'],
            $validated['qty']
        );

        return response()->json($result);
    }
}
