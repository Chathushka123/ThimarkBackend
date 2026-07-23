<?php

namespace App;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Shift
 *
 * @property int $id
 * @property string $shift_code
 * @property string $shift_name
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class Shift extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'shifts';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'shift_code',
        'shift_name',
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

        static::creating(function (Shift $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (Shift $model) {
            $model->updated_by = Auth::id();
        });
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
