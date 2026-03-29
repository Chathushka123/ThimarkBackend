<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    private $repo;

    public function __construct()
    {
        $this->repo = new \App\Http\Repositories\SupplierRepository();
    }

    // Get all suppliers
    public function index()
    {
        return $this->repo->getSuppliers();
    }

    // Get supplier by id
    public function show($id)
    {
        return $this->repo->getSupplier($id) ?: response()->json(['message' => 'Supplier not found'], 404);
    }

    // Create supplier
    public function store(Request $request)
    {
        return $this->repo->createSupplier($request);
    }

    // Update supplier
    public function update(Request $request, $id)
    {
        return $this->repo->updateSupplier($request, $id);
    }
}
