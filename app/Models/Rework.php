<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Rework
 *
 * @property int $id
 * @property int $bundle_ticket_id
 * @property int $rework_qty
 * @property int $return_qty
 * @property int $daily_shift_team_id
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class Rework extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'reworks';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'bundle_ticket_id',
        'rework_qty',
        'return_qty',
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

        static::creating(function (Rework $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (Rework $model) {
            $model->updated_by = Auth::id();
        });
    }

    /**
     * The bundle ticket this rework belongs to.
     */
    public function bundleTicket()
    {
        return $this->belongsTo(BundleTicket::class, 'bundle_ticket_id');
    }

    /**
     * The daily shift team that recorded this rework.
     */
    public function dailyShiftTeam()
    {
        return $this->belongsTo(DailyShiftTeam::class, 'daily_shift_team_id');
    }
}
