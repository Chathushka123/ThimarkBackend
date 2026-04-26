<?php

namespace App\Http\Repositories;

use App\WhlItem;
use App\WarehouseLocation;
use App\InventoryLog;
use App\StockMaterial;
use Illuminate\Support\Facades\DB;

class WhlItemRepository
{
    public function all()
    {
        return WhlItem::with(['warehouseLocation', 'stockItem'])->get(); // Model global scope already filters active=true
    }

    public function find($id)
    {
        return WhlItem::with(['warehouseLocation', 'stockItem'])->findOrFail($id); // Model global scope already filters active=true
    }

    public function create(array $data)
    {
        unset($data['grn_price']);

        $bin = WarehouseLocation::with('warehouse')->findOrFail($data['whl_id']);
        $stock_material = StockMaterial::findOrFail($bin->stock_item_id);
        if ($bin->warehouse->location_basis == 1 && $bin->stock_item_id != null) {

            if ($bin->stock_item_id != $data['stock_item_id']) {
                throw new \InvalidArgumentException(
                    "This bin already Map a different stock material ({$stock_material->code}). "
                );
            }
        }

        $whlItem = WhlItem::create($data);

        InventoryLog::create([
            'wh_id'             => $bin->warehouse_id,
            'bin_id'            => $whlItem->whl_id,
            'stock_material_id' => $whlItem->stock_item_id,
            'whl_item_id'       => $whlItem->id,
            'log_type'          => 'Add a material',
            'previous_qty'      => null,
            'new_qty'           => $whlItem->qty,
        ]);

        return $whlItem;
    }

    public function update($id, array $data)
    {
        $whlItem = WhlItem::with('warehouseLocation')->findOrFail($id);
        $previousQty = $whlItem->qty;
        unset($data['grn_price']);
        $whlItem->update($data);

        if (isset($data['qty']) && $data['qty'] != $previousQty) {
            InventoryLog::create([
                'wh_id'             => $whlItem->warehouseLocation->warehouse_id,
                'bin_id'            => $whlItem->whl_id,
                'stock_material_id' => $whlItem->stock_item_id,
                'whl_item_id'       => $whlItem->id,
                'log_type'          => 'Quantity adjustment',
                'previous_qty'      => $previousQty,
                'new_qty'           => $whlItem->qty,
            ]);
        }

        return $whlItem;
    }

    public function delete($id)
    {
        $whlItem = WhlItem::findOrFail($id);
        $whlItem->delete(); // Triggers soft-delete logic (active=false)
        return true;
    }

    public function moveBin(int $fromBinId, int $toBinId, int $materialId, $qty): array
    {
        return DB::transaction(function () use ($fromBinId, $toBinId, $materialId, $qty) {
            if ($fromBinId === $toBinId) {
                throw new \InvalidArgumentException('Source and destination bins must be different.');
            }

            $source = WhlItem::lockForUpdate()
                ->where('whl_id', $fromBinId)
                ->where('stock_item_id', $materialId)
                ->firstOrFail();

            if ($qty <= 0) {
                throw new \InvalidArgumentException('Transfer quantity must be greater than zero.');
            }

            if ($qty > $source->qty) {
                throw new \InvalidArgumentException(
                    "Transfer quantity ({$qty}) exceeds available stock ({$source->qty})."
                );
            }

            $fromBin = WarehouseLocation::with('warehouse')->findOrFail($fromBinId);
            $toBin   = WarehouseLocation::findOrFail($toBinId);

            if ($fromBin->warehouse_id !== $toBin->warehouse_id) {
                throw new \InvalidArgumentException('Source and destination bins must belong to the same warehouse.');
            }

            if ($fromBin->warehouse->location_basis == 1) {
                $conflict = WhlItem::where('whl_id', $toBinId)
                    ->where('stock_item_id', '!=', $materialId)
                    ->exists();
                if ($conflict) {
                    throw new \InvalidArgumentException(
                        'Destination bin already contains a different stock material. A location-basis-1 warehouse allows only one material per bin.'
                    );
                }
            }

            $previousQty  = $source->qty;
            $remainingQty = $source->qty - $qty;
            if ($remainingQty == 0) {
                $source->delete();
            } else {
                $source->qty = $remainingQty;
                $source->save();
            }

            $destination = WhlItem::where('whl_id', $toBinId)
                ->where('stock_item_id', $materialId)
                ->first();

            if ($destination) {
                $destination->qty += $qty;
                $destination->save();
            } else {
                $destination = WhlItem::create([
                    'whl_id'        => $toBinId,
                    'stock_item_id' => $materialId,
                    'qty'           => $qty,
                ]);
            }

            InventoryLog::create([
                'wh_id'             => $fromBin->warehouse_id,
                'bin_id'            => $fromBinId,
                'stock_material_id' => $materialId,
                'whl_item_id'       => $source->id,
                'log_type'          => 'Transfer to a new bin',
                'previous_qty'      => $previousQty,
                'new_qty'           => $remainingQty,
                'old_bin'           => $fromBinId,
                'new_bin'           => $toBinId,
            ]);

            return [
                'from_bin_id'     => $fromBinId,
                'to_bin_id'       => $toBinId,
                'material_id'     => $materialId,
                'transferred_qty' => $qty,
                'source_remaining_qty' => $remainingQty,
                'destination_qty' => $destination->qty,
            ];
        });
    }
}
