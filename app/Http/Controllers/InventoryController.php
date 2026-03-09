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

    /**
     * POST /api/v1/inventory/transfer
     * Transfer a stock material between bins (same or different racks, full or partial qty).
     *
     * Body:
     *   whl_item_id  (int)   — source whl_item to transfer from
     *   to_whl_id    (int)   — destination warehouse_location (bin) id
     *   qty          (numeric) — quantity to transfer
     */
    public function transferStock(Request $request)
    {
        $validated = $request->validate([
            'whl_item_id' => 'required|integer|exists:whl_items,id',
            'to_whl_id'   => 'required|integer|exists:warehouse_locations,id',
            'qty'         => 'required|numeric|min:0.001',
        ]);

        try {
            $result = $this->repository->transferStock(
                (int) $validated['whl_item_id'],
                (int) $validated['to_whl_id'],
                $validated['qty']
            );
            return response()->json($result, 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
