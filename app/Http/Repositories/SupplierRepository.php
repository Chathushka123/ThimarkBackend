<?php

namespace App\Http\Repositories;

use App\Supplier;
use Illuminate\Support\Facades\Validator;
use Exception;

class SupplierRepository
{
    public function getSuppliers()
    {
        return Supplier::all();
    }

    public function getSupplier($id)
    {
        return Supplier::find($id);
    }

    public function createSupplier($request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'contact_no' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        try {
            $supplier = Supplier::create($request->only(['name', 'address', 'contact_no', 'email']));
            return response()->json($supplier, 201);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function updateSupplier($request, $id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string',
            'contact_no' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        try {
            $supplier->update($request->only(['name', 'address', 'contact_no', 'email']));
            return response()->json($supplier);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
