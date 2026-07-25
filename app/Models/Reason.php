<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Reason
 *
 * Master lookup table for reject/rework reasons shown as dropdowns on the
 * Production WIP Scanning screen.
 *
 * @property int $id
 * @property string $code
 * @property string $description
 * @property string $type REJECT|REWORK
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class Reason extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'reasons';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'description',
        'type',
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

        static::creating(function (Reason $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (Reason $model) {
            $model->updated_by = Auth::id();
        });
    }
}
