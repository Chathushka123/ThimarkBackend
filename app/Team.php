<?php

namespace App;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Team
 *
 * @property int $id
 * @property string $team_code
 * @property string $team_name
 * @property int $no_of_operators
 * @property int|null $operation_id
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
        'no_of_operators',
        'operation_id',
        'active',
        'created_by',
        'updated_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'no_of_operators' => 'integer',
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

    /**
     * The operation this team performs.
     */
    public function operation()
    {
        return $this->belongsTo(OperationMaster::class, 'operation_id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
