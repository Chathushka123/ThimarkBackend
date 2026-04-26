<?php

namespace App\Http\Repositories;

use App\Warehouse;
use App\WarehouseLocation;
use App\WhlItem;
use Illuminate\Support\Facades\DB;

class WarehouseRepository
{
    public function all()
    {
        return Warehouse::with('locations')->get(); // Model global scope already filters active=true
    }

    public function find($id)
    {
        return Warehouse::with('locations')->findOrFail($id); // Model global scope already filters active=true
    }

    public function create(array $data)
    {
        $locations = $data['locations'] ?? [];
        unset($data['locations']);
        $warehouse = Warehouse::create($data);
        foreach ($locations as $loc) {
            $warehouse->locations()->create($loc);
        }
        return $warehouse->load('locations');
    }

    public function update($id, array $data)
    {
        $warehouse = Warehouse::findOrFail($id);
        $locations = $data['locations'] ?? [];
        unset($data['locations']);

        if (isset($data['active']) && $data['active'] == 0) {
            $hasStock = $warehouse->locations()
                ->whereHas('whlItems', fn($q) => $q->where('qty', '>', 0))
                ->exists();

            if ($hasStock) {
                throw new \Exception('Cannot deactivate warehouse: one or more locations have items with stock quantity greater than zero.');
            }
        }

        $warehouse->update($data);
        foreach ($locations as $loc) {
            if (isset($loc['id'])) {
                $location = $warehouse->locations()->find($loc['id']);
                if ($location) {
                    if (isset($loc['active']) && $loc['active'] == 0) {
                        $hasStock = $location->whlItems()->where('qty', '>', 0)->exists();
                        if ($hasStock) {
                            throw new \Exception("Cannot deactivate location {$location->id}: it has items with stock quantity greater than zero.");
                        }
                    }
                    $location->update($loc);
                }
            } else {
                $warehouse->locations()->create($loc);
            }
        }
        return $warehouse->load('locations');
    }

    public function delete($id)
    {
        $warehouse = Warehouse::findOrFail($id);

        $hasStock = $warehouse->locations()
            ->whereHas('whlItems', fn($q) => $q->where('qty', '>', 0))
            ->exists();

        if ($hasStock) {
            throw new \Exception('Cannot deactivate warehouse: one or more locations have items with stock quantity greater than zero.');
        }

        $warehouse->locations()->where('active', 1)->update(['active' => 0]);
        $warehouse->active = 0;
        $warehouse->save();
        return true;
    }

    public function getActiveLocations($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        return $warehouse->locations()->where('active', 1)
            ->with(['stockMaterial:id,name,code'])
            ->orderBy('rack')->orderBy('id')->get();
    }

    public function getWarehouseStructure($id)
    {
        $warehouse = Warehouse::findOrFail($id);

        // Global scopes on WarehouseLocation and WhlItem already filter active=true
        // $locations = $warehouse->locations()->where('active', 1)->with([
        //     'whlItems' => fn($q) => $q->select('whl_id', 'stock_item_id', DB::raw('SUM(qty) as qty'))
        //         ->groupBy('whl_id', 'stock_item_id'),
        //     'whlItems.stockItem',
        // ])->get();

        $locations = $warehouse->locations()->where('active', 1)->with(['whlItems' => fn($q) => $q->where('qty', '>', 0), 'whlItems.stockItem'])->get();

        // Group locations by rack, then collect bins per rack
        $racksMap = [];
        foreach ($locations as $location) {
            $rack = $location->rack ?? 'N/A';

            if (!isset($racksMap[$rack])) {
                $racksMap[$rack] = [];
            }

            $items = $location->whlItems->map(function ($whlItem) {
                return [
                    'id'            => $whlItem->id,
                    'whl_id'        => $whlItem->whl_id,
                    'stock_item_id' => $whlItem->stock_item_id,
                    'qty'           => $whlItem->qty,
                    'stock_item'    => $whlItem->stockItem,
                ];
            })->values();

            $racksMap[$rack][] = [
                'id'    => $location->id,
                'bin'   => $location->bin,
                'items' => $items,
            ];
        }

        $result = [];
        foreach ($racksMap as $rack => $bins) {
            $result[] = [
                'rack' => $rack,
                'bins' => $bins,
            ];
        }

        return $result;
    }

    /**
     * Retrieve a previously saved inventory snapshot for a warehouse on a given date.
     * Looks in local storage first; falls back to Google Drive.
     */
    public function getSnapshot(int $warehouseId, string $date): ?array
    {
        $filename = "warehouse_{$warehouseId}_{$date}.json";
        $localPath = "inventory-snapshots/{$filename}";

        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($localPath)) {
            $json = \Illuminate\Support\Facades\Storage::disk('local')->get($localPath);
        } elseif (\Illuminate\Support\Facades\Storage::disk('google')->exists($filename)) {
            $json = \Illuminate\Support\Facades\Storage::disk('google')->get($filename);
        } else {
            return null;
        }

        return json_decode($json, true);
    }

    /**
     * Transfer stock from one bin (warehouse_location) to another.
     *
     * @param  int   $whlItemId   Source whl_item id
     * @param  int   $toWhlId     Destination warehouse_location id
     * @param  float $qty         Quantity to transfer (must be > 0 and <= source qty)
     * @return array
     * @throws \Exception
     */
    public function transferStock(int $whlItemId, int $toWhlId, $qty): array
    {
        return DB::transaction(function () use ($whlItemId, $toWhlId, $qty) {
            /** @var WhlItem $source */
            $source = WhlItem::lockForUpdate()->findOrFail($whlItemId);

            if ($qty <= 0) {
                throw new \InvalidArgumentException('Transfer quantity must be greater than zero.');
            }

            if ($qty > $source->qty) {
                throw new \InvalidArgumentException(
                    "Transfer quantity ({$qty}) exceeds available stock ({$source->qty})."
                );
            }

            // Ensure destination bin exists (scope already filters active=true)
            $destinationBin = WarehouseLocation::findOrFail($toWhlId);

            if ($source->whl_id === $toWhlId) {
                throw new \InvalidArgumentException('Source and destination bins must be different.');
            }

            // Deduct from source
            $remainingQty = $source->qty - $qty;
            if ($remainingQty == 0) {
                $source->delete(); // soft-delete (active=false via boot)
            } else {
                $source->qty = $remainingQty;
                $source->save();
            }

            // Add to destination — merge with existing whl_item for same stock material, or create new
            $destination = WhlItem::where('whl_id', $toWhlId)
                ->where('stock_item_id', $source->stock_item_id)
                ->first();

            if ($destination) {
                $destination->qty += $qty;
                $destination->save();
            } else {
                $destination = WhlItem::create([
                    'whl_id'        => $toWhlId,
                    'stock_item_id' => $source->stock_item_id,
                    'qty'           => $qty,
                ]);
            }

            return [
                'source' => [
                    'whl_item_id'      => $whlItemId,
                    'whl_id'           => $source->whl_id,
                    'stock_item_id'    => $source->stock_item_id,
                    'remaining_qty'    => $remainingQty,
                    'fully_transferred' => $remainingQty == 0,
                ],
                'destination' => [
                    'whl_item_id'   => $destination->id,
                    'whl_id'        => $destination->whl_id,
                    'bin'           => $destinationBin->bin,
                    'rack'          => $destinationBin->rack,
                    'stock_item_id' => $destination->stock_item_id,
                    'new_qty'       => $destination->qty,
                ],
                'transferred_qty' => $qty,
            ];
        });
    }
}
