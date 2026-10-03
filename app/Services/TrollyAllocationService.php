<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\BundleTicket;
use App\TrollyMaster;
use Illuminate\Support\Facades\Log;

/**
 * Owns the trolly <-> bundle occupancy lifecycle.
 *
 * A trolly is a physical carrier: once a bundle is loaded onto one at
 * bundle creation, that same trolly carries it through every operation and
 * direction on the bundle's route — nothing in the scanning flow moves a
 * bundle between trollies mid-route. The trolly becomes free again only
 * when the bundle has finished its LAST operation and direction, at which
 * point the physical trolly is emptied on the floor and must become
 * available for the next bundle.
 *
 * Occupancy is held on `trolly_masters` (`bundle_id` + `used`), and
 * `trolly_masters.bundle_id` is the authoritative "what is loaded on this
 * trolly right now" link — it is what the scanning screen's trolly/bundle
 * cross-resolve already reads, and the only one of the three columns that
 * survived the drop/re-add migration churn (000006→000009 wiped
 * `bundles.trolly_master_id`, 000007→000010 wiped `used`), so existing rows
 * can legitimately have it set while the other two are empty.
 *
 * `bundles.trolly_master_id` is only a permanent record of which trolly
 * carried the bundle, kept for traceability after the trolly is handed on.
 * The two are therefore NOT always mirror images: a completed bundle still
 * names its trolly while that trolly may already be carrying someone else's
 * work. Every write here is guarded on the live side so a completed bundle
 * can never unload a trolly it no longer occupies.
 *
 * ADR: modelled as a service rather than a model event on BundleTicketSecondary
 * because completion is a route-wide ledger conclusion, not a row-level fact —
 * it needs BundleLedgerService, which an Eloquent observer has no clean way to
 * reach without recursing into the same locks the scan write already holds.
 */
class TrollyAllocationService
{
    private BundleLedgerService $ledger;

    public function __construct(BundleLedgerService $ledger)
    {
        $this->ledger = $ledger;
    }

    /**
     * Re-evaluate whether $bundle should still be occupying its trolly and
     * release or re-attach it to match.
     *
     * Idempotent and safe to call after every scanning write, including ones
     * that change nothing — which is the point: callers don't have to reason
     * about whether their particular write was the completing one.
     *
     * @param bool|null $bundleComplete Pass the caller's already-computed
     *        completion flag (from the ledger it just built) to avoid a second
     *        ledger build; omit to have this service derive it itself.
     * @return array{trolly_id:int, trolly_code:string|null, action:string}|null
     *         null when the bundle has no trolly on record, or the trolly row
     *         is gone, or nothing needed to change.
     */
    public function sync(Bundle $bundle, ?bool $bundleComplete = null): ?array
    {
        // Live occupancy first. The bundle's own `trolly_master_id` is the
        // fallback because after a release it is the only clue left as to
        // which trolly to hand back if the bundle gets reopened.
        $trolly = TrollyMaster::where('bundle_id', $bundle->id)->lockForUpdate()->first();

        if (!$trolly && $bundle->trolly_master_id !== null) {
            $trolly = TrollyMaster::where('id', (int) $bundle->trolly_master_id)->lockForUpdate()->first();
        }

        if (!$trolly) {
            return null;
        }

        // Record the bundle's own link while the live one still tells us what
        // it is: after a release it is the only trace of which trolly carried
        // this bundle, and it is NULL on every row predating the column's
        // re-add. Written with a query update so the bundle's `updated_by`
        // audit field isn't overwritten with the scanning operator (or, from
        // the reconcile command, with null) for what is a bookkeeping repair.
        if ((int) $trolly->bundle_id === (int) $bundle->id
            && (int) $bundle->trolly_master_id !== (int) $trolly->id) {
            Bundle::where('id', $bundle->id)->update(['trolly_master_id' => $trolly->id]);
            $bundle->trolly_master_id = $trolly->id;
            $bundle->syncOriginal();
        }

        if ($bundleComplete === null) {
            $bundleComplete = $this->ledger->build($bundle)['bundleComplete'];
        }

        return $bundleComplete
            ? $this->release($bundle, $trolly)
            : $this->reattach($bundle, $trolly);
    }

    /**
     * sync() for callers holding only a bundle id (and no loaded model).
     */
    public function syncByBundleId(?int $bundleId, ?bool $bundleComplete = null): ?array
    {
        if ($bundleId === null) {
            return null;
        }

        $bundle = Bundle::where('id', $bundleId)->where('active', true)->first();

        return $bundle ? $this->sync($bundle, $bundleComplete) : null;
    }

    /**
     * sync() for callers holding only a bundle ticket — the undo endpoints,
     * which are addressed by scan-entry id and have to walk back up to the
     * bundle whose completion state their undo may have just reversed.
     */
    public function syncByTicketId(?int $bundleTicketId): ?array
    {
        if ($bundleTicketId === null) {
            return null;
        }

        $ticket = BundleTicket::where('id', $bundleTicketId)->first();

        return $ticket ? $this->syncByBundleId((int) $ticket->bundle_id) : null;
    }

    /**
     * Free the trolly: the bundle has cleared its final operation/direction,
     * so the physical trolly is empty and available for the next bundle.
     */
    private function release(Bundle $bundle, TrollyMaster $trolly): ?array
    {
        // Already released (re-scan of an already-complete bundle, a retry,
        // or a second completing write) — or occupied by a different bundle,
        // which we must never steal from.
        if ($trolly->bundle_id === null || (int) $trolly->bundle_id !== (int) $bundle->id) {
            // `used` without an occupant is a stale half-state (see the
            // class docblock on the migration churn) — clear it so the
            // trolly doesn't stay unusable forever.
            if ($trolly->bundle_id === null && $trolly->used) {
                $trolly->used = false;
                $trolly->save();

                return ['trolly_id' => (int) $trolly->id, 'trolly_code' => $trolly->code, 'action' => 'cleared-stale-flag'];
            }

            return null;
        }

        $trolly->bundle_id = null;
        $trolly->used = false;
        $trolly->save();

        Log::info('Trolly released: bundle completed its route', [
            'context' => 'trolly_allocation',
            'trolly_id' => $trolly->id,
            'trolly_code' => $trolly->code,
            'bundle_id' => $bundle->id,
            'work_order_id' => $bundle->work_order_id,
        ]);

        return ['trolly_id' => (int) $trolly->id, 'trolly_code' => $trolly->code, 'action' => 'released'];
    }

    /**
     * Re-occupy the trolly for a bundle that is no longer complete.
     *
     * Reached when an operator undoes a scan that had completed the bundle
     * (or returns a rework batch to outstanding), which puts the bundle back
     * on the floor — and therefore back on its trolly. If the trolly was
     * already handed to another bundle in the meantime we cannot take it
     * back; that is reported, not forced, so the floor can re-allocate.
     */
    private function reattach(Bundle $bundle, TrollyMaster $trolly): ?array
    {
        // The overwhelmingly common case: still held, nothing to do.
        if ((int) $trolly->bundle_id === (int) $bundle->id && $trolly->used) {
            return null;
        }

        if (($trolly->bundle_id !== null && (int) $trolly->bundle_id !== (int) $bundle->id) || !$trolly->active) {
            Log::warning('Trolly unavailable for re-attach after bundle reopened', [
                'context' => 'trolly_allocation',
                'trolly_id' => $trolly->id,
                'trolly_code' => $trolly->code,
                'bundle_id' => $bundle->id,
                'held_by_bundle_id' => $trolly->bundle_id,
                'trolly_active' => (bool) $trolly->active,
            ]);

            return ['trolly_id' => (int) $trolly->id, 'trolly_code' => $trolly->code, 'action' => 'unavailable'];
        }

        $trolly->bundle_id = $bundle->id;
        $trolly->used = true;
        $trolly->save();

        Log::info('Trolly re-attached: bundle reopened before completion', [
            'context' => 'trolly_allocation',
            'trolly_id' => $trolly->id,
            'trolly_code' => $trolly->code,
            'bundle_id' => $bundle->id,
        ]);

        return ['trolly_id' => (int) $trolly->id, 'trolly_code' => $trolly->code, 'action' => 'reattached'];
    }
}
