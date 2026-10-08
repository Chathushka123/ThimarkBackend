<?php

namespace App\Console\Commands;

use App\Models\Bundle;
use App\Services\TrollyAllocationService;
use App\TrollyMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Re-derives trolly occupancy from the bundle ledger for every bundle that
 * holds one.
 *
 * Needed as a one-off backfill because trollies were previously only ever
 * released when someone manually re-assigned a bundle to a different one —
 * so every bundle that had already finished its route was still holding its
 * trolly as `used`, slowly draining the available pool. Kept as a command
 * (rather than a data migration) so it stays available as a repair tool:
 * it's idempotent and safe to re-run any time occupancy looks wrong.
 */
class ReconcileTrollyAllocations extends Command
{
    protected $signature = 'trolly:reconcile
                            {--dry-run : Report what would change without writing}
                            {--bundle= : Reconcile a single bundle id instead of all}';

    protected $description = 'Release trollies still held by bundles that have finished their route (and re-attach any wrongly freed)';

    public function handle(TrollyAllocationService $allocation): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Reachable from either side of the link: trollies currently
        // declaring an occupant (the authoritative live column), plus
        // bundles naming a trolly (which is all that's left once a trolly
        // has been released, and the only way to spot one wrongly freed).
        $occupantIds = TrollyMaster::whereNotNull('bundle_id')->pluck('bundle_id');

        $query = Bundle::where('active', true)
            ->where(fn ($q) => $q->whereNotNull('trolly_master_id')->orWhereIn('id', $occupantIds));

        if ($this->option('bundle')) {
            $query->where('id', (int) $this->option('bundle'));
        }

        $bundles = $query->orderBy('id')->get();

        if ($bundles->isEmpty()) {
            $this->info('No bundles are linked to a trolly.');
            $this->reportOrphans($occupantIds);

            return self::SUCCESS;
        }

        $rows = [];
        $changed = 0;
        $failed = 0;

        foreach ($bundles as $bundle) {
            // One transaction per bundle: a single bad row must not roll
            // back the reconciliation of all the others. --dry-run runs the
            // real service and discards the write, so what it reports is
            // exactly what a live run would do.
            DB::beginTransaction();

            try {
                $result = $allocation->sync($bundle);
                $dryRun ? DB::rollBack() : DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                $failed++;
                $this->error("Bundle {$bundle->id}: {$e->getMessage()}");
                continue;
            }

            if ($result === null) {
                continue;
            }

            $changed++;
            $rows[] = [
                $bundle->id,
                $result['trolly_id'],
                $result['trolly_code'] ?? '-',
                $result['action'],
            ];
        }

        if ($rows) {
            $this->table(['Bundle', 'Trolly ID', 'Trolly Code', 'Action'], $rows);
        }

        $verb = $dryRun ? 'would change' : 'changed';
        $this->info("Checked {$bundles->count()} bundle(s); {$verb} {$changed}.");

        $this->reportOrphans($occupantIds);

        if ($failed > 0) {
            $this->warn("{$failed} bundle(s) failed — see errors above.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Flag trollies declaring an occupant that no longer resolves to an
     * active bundle. Deliberately reported, not auto-fixed: an unexpected
     * dangling occupant usually means a bundle was deleted or deactivated
     * out from under its trolly, which is worth a human look.
     */
    private function reportOrphans(\Illuminate\Support\Collection $occupantIds): void
    {
        if ($occupantIds->isEmpty()) {
            return;
        }

        $liveIds = Bundle::where('active', true)->whereIn('id', $occupantIds)->pluck('id');
        $orphans = TrollyMaster::whereNotNull('bundle_id')
            ->whereNotIn('bundle_id', $liveIds->isEmpty() ? [0] : $liveIds->all())
            ->get();

        if ($orphans->isEmpty()) {
            return;
        }

        $this->warn('Trollies pointing at a missing or inactive bundle (not auto-fixed):');
        foreach ($orphans as $trolly) {
            $this->line("  trolly #{$trolly->id} ({$trolly->code}) -> bundle {$trolly->bundle_id}");
        }
        $this->line('Free these from the bundle edit screen, or confirm the bundle should be active.');
    }
}
