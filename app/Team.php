<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Team
 *
 * @property int $id
 * @property string $team_code
 * @property string $team_name
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class Team extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'teams';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'team_code',
        'team_name',
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

        static::creating(function (Team $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (Team $model) {
            $model->updated_by = Auth::id();
        });
    }
}
