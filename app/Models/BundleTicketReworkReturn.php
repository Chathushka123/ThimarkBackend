<?php

namespace App\Models;

use App\DailyShiftTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * BundleTicketReworkReturn
 *
 * Records the rework team's own scan-out: how much of an outstanding
 * BundleTicketRework qty came back good (return_qty) vs was permanently
 * rejected after rework (reject_qty), against the same bundle_ticket the
 * original send-to-rework came from. Creating this row also creates the
 * matching BundleTicketSecondary (source=REWORK_RETURN) and/or
 * BundleTicketReject (source=REWORK_REJECT) rows so the normal scan ledger
 * picks the resolved qty up automatically — see WipScanController::returnFromRework().
 *
 * @property int $id
 * @property int $bundle_ticket_id
 * @property int $return_qty
 * @property int $reject_qty
 * @property int|null $reason_id
 * @property string|null $remarks
 * @property int $daily_shift_team_id
 * @property int|null $bundle_ticket_secondary_id
 * @property int|null $bundle_ticket_reject_id
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class BundleTicketReworkReturn extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'bundle_ticket_rework_returns';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'bundle_ticket_id',
        'return_qty',
        'reject_qty',
        'reason_id',
        'remarks',
        'daily_shift_team_id',
        'bundle_ticket_secondary_id',
        'bundle_ticket_reject_id',
        'active',
        'created_by',
        'updated_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Bootstrap model event listeners for audit fields.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (BundleTicketReworkReturn $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (BundleTicketReworkReturn $model) {
            $model->updated_by = Auth::id();
        });
    }

    /**
     * The bundle ticket this return resolves rework against.
     */
    public function bundleTicket()
    {
        return $this->belongsTo(BundleTicket::class, 'bundle_ticket_id');
    }

    /**
     * The reason given if any qty came back still rejected.
     */
    public function reason()
    {
        return $this->belongsTo(Reason::class, 'reason_id');
    }

    /**
     * The daily shift team (rework team) that recorded this return.
     */
    public function dailyShiftTeam()
    {
        return $this->belongsTo(DailyShiftTeam::class, 'daily_shift_team_id');
    }

    /**
     * The good-qty scan entry this return created, if any.
     */
    public function bundleTicketSecondary()
    {
        return $this->belongsTo(BundleTicketSecondary::class, 'bundle_ticket_secondary_id');
    }

    /**
     * The reject entry this return created, if any.
     */
    public function bundleTicketReject()
    {
        return $this->belongsTo(BundleTicketReject::class, 'bundle_ticket_reject_id');
    }
}
