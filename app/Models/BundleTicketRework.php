<?php

namespace App\Models;

use App\DailyShiftTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * BundleTicketRework
 *
 * A qty pulled out of the normal scan/reject flow and sent to the rework
 * team, against the ticket (operation x direction) it was flagged at. Until
 * a matching BundleTicketReworkReturn resolves it, this qty counts as
 * "outstanding" — accounted for, but not yet available to the next
 * operation.
 *
 * @property int $id
 * @property int $bundle_ticket_id
 * @property int $rework_qty
 * @property int $reason_id
 * @property string|null $remarks
 * @property int $daily_shift_team_id
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class BundleTicketRework extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'bundle_ticket_reworks';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'bundle_ticket_id',
        'rework_qty',
        'reason_id',
        'remarks',
        'daily_shift_team_id',
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

        static::creating(function (BundleTicketRework $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (BundleTicketRework $model) {
            $model->updated_by = Auth::id();
        });
    }

    /**
     * The bundle ticket this rework was sent from.
     */
    public function bundleTicket()
    {
        return $this->belongsTo(BundleTicket::class, 'bundle_ticket_id');
    }

    /**
     * The reason this qty was sent to rework.
     */
    public function reason()
    {
        return $this->belongsTo(Reason::class, 'reason_id');
    }

    /**
     * The daily shift team that recorded this rework send.
     */
    public function dailyShiftTeam()
    {
        return $this->belongsTo(DailyShiftTeam::class, 'daily_shift_team_id');
    }
}
