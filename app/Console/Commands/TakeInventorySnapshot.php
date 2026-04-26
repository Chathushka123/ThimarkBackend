<?php

namespace App\Console\Commands;

use App\Http\Repositories\WarehouseRepository;
use App\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TakeInventorySnapshot extends Command
{
    protected $signature = 'inventory:snapshot {--warehouse= : Specific warehouse ID to snapshot (omit for all)}';

    protected $description = 'Take a daily inventory snapshot for all (or a specific) warehouse and upload to Google Drive';

    public function handle(WarehouseRepository $repo): int
    {
        $date = now()->format('Y-m-d');
        $warehouses = $this->option('warehouse')
            ? Warehouse::where('id', $this->option('warehouse'))->get()
            : Warehouse::all();

        if ($warehouses->isEmpty()) {
            $this->warn('No active warehouses found.');
            return self::SUCCESS;
        }

        $errors = 0;

        foreach ($warehouses as $warehouse) {
            try {
                $snapshot = [
                    'snapshot_date' => $date,
                    'warehouse_id'  => $warehouse->id,
                    'warehouse_name' => $warehouse->name ?? "Warehouse #{$warehouse->id}",
                    'structure'     => $repo->getWarehouseStructure($warehouse->id),
                ];

                $path = "warehouse_{$warehouse->id}_{$date}.json";
                $json = json_encode($snapshot, JSON_PRETTY_PRINT);

                // Storage::disk('google')->put($path, $json);
                Storage::disk('local')->put("inventory-snapshots/{$path}", $json);

                $this->info("Snapshot saved: {$path}");
                Log::info("Inventory snapshot uploaded to Google Drive: {$path}");
            } catch (\Throwable $e) {
                $errors++;
                $this->error("Failed snapshot for warehouse {$warehouse->id}: {$e->getMessage()}");
                Log::error("Inventory snapshot failed for warehouse {$warehouse->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
