<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * RouteMaster
 *
 * Master lookup table for routings. Audit fields (created_by / updated_by)
 * are populated automatically via model events and are intentionally kept
 * out of $fillable so they can never be mass-assigned from a request.
 *
 * @property int $id
 * @property string $route_code
 * @property string|null $description
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class RouteMaster extends Model
{
    /**
     * @var string
     */
    protected $table = 'route_masters';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'route_code',
        'description',
        'active',
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

        static::creating(function (RouteMaster $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (RouteMaster $model) {
            $model->updated_by = Auth::id();
        });
    }

    /**
     * User who created this record.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated this record.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
