<?php

namespace App\Http\Controllers;

use App\Http\Repositories\MainModelRepository;
use Illuminate\Http\Request;

class MainModelController extends Controller
{
    protected $mainModelRepository;

    public function __construct(MainModelRepository $mainModelRepository)
    {
        $this->mainModelRepository = $mainModelRepository;
    }

    public function index()
    {
        return response()->json($this->mainModelRepository->all());
    }

    public function show($id)
    {
        return response()->json($this->mainModelRepository->find($id));
    }

    public function store(Request $request)
    {
        $data = $request->only(['name']);
        return response()->json($this->mainModelRepository->create($data));
    }

    public function update(Request $request, $id)
    {
        $data = $request->only(['name']);
        return response()->json($this->mainModelRepository->update($id, $data));
    }

    public function destroy($id)
    {
        $this->mainModelRepository->delete($id);
        return response()->json(['message' => 'Deleted successfully']);
    }
}
