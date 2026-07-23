<?php

namespace App;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * DailyShiftTeam
 *
 * @property int $id
 * @property int $daily_shift_id
 * @property int $team_id
 * @property string $start_date_time
 * @property string $end_date_time
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class DailyShiftTeam extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'daily_shift_teams';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'daily_shift_id',
        'team_id',
        'start_date_time',
        'end_date_time',
        'active',
        'created_by',
        'updated_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'start_date_time' => 'datetime',
        'end_date_time' => 'datetime',
        'active' => 'boolean',
    ];

    /**
     * Bootstrap model event listeners for audit fields.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (DailyShiftTeam $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (DailyShiftTeam $model) {
            $model->updated_by = Auth::id();
        });
    }

    /**
     * The daily shift this record belongs to.
     */
    public function dailyShift()
    {
        return $this->belongsTo(DailyShift::class, 'daily_shift_id');
    }

    /**
     * The team assigned to this daily shift.
     */
    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
