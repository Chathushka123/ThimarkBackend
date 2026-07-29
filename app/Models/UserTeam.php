<?php

namespace App\Models;

use App\Team;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * UserTeam
 *
 * Pivot table assigning a user to a shift Team they are allowed to work as
 * on the Production WIP Scanning screen. A user may be assigned more than
 * one team.
 *
 * @property int $id
 * @property int $user_id
 * @property int $team_id
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class UserTeam extends Model
{
    /**
     * @var string
     */
    protected $table = 'user_teams';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'team_id',
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

        static::creating(function (UserTeam $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (UserTeam $model) {
            $model->updated_by = Auth::id();
        });
    }

    /**
     * The user this assignment belongs to.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The team this assignment references.
     */
    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }
}
