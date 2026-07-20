<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * BundleTicketSecondary
 *
 * @property int $id
 * @property int $bundle_ticket_id
 * @property int $scan_qty
 * @property int $daily_shift_team_id
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class BundleTicketSecondary extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'bundle_ticket_secondaries';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'bundle_ticket_id',
        'scan_qty',
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

        static::creating(function (BundleTicketSecondary $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (BundleTicketSecondary $model) {
            $model->updated_by = Auth::id();
        });
    }

    /**
     * The bundle ticket this scan belongs to.
     */
    public function bundleTicket()
    {
        return $this->belongsTo(BundleTicket::class, 'bundle_ticket_id');
    }

    /**
     * The daily shift team that performed this scan.
     */
    public function dailyShiftTeam()
    {
        return $this->belongsTo(DailyShiftTeam::class, 'daily_shift_team_id');
    }
}
