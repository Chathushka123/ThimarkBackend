<?php

namespace App\Http\Controllers;

use App\Http\Repositories\WarehouseRepository;
use Illuminate\Http\Request;

class InventoryController extends Controller
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

    /**
     * GET /api/v1/inventory/warehouse/{id}
     * Returns active racks → bins → items for the given warehouse.
     */
    public function getWarehouseStructure($id)
    {
        return response()->json($this->repository->getWarehouseStructure($id));
    }
}
