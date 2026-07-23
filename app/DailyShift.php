<?php

namespace App;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * DailyShift
 *
 * @property int $id
 * @property int $shift_id
 * @property string $shift_date
 * @property string $start_date_time
 * @property string $end_date_time
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class DailyShift extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'daily_shifts';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'shift_id',
        'shift_date',
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
        // Explicit format so array/JSON serialization emits a plain "Y-m-d"
        // date string — a bare 'date' cast would instead be serialized via
        // serializeDate() below (same as start_date_time/end_date_time),
        // which formats with a "H:i:s" suffix.
        'shift_date' => 'date:Y-m-d',
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

        static::creating(function (DailyShift $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (DailyShift $model) {
            $model->updated_by = Auth::id();
        });
    }

    /**
     * The shift this daily record belongs to.
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    /**
     * The team assignments for this daily shift.
     */
    public function daily_shift_teams()
    {
        return $this->hasMany(DailyShiftTeam::class, 'daily_shift_id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
