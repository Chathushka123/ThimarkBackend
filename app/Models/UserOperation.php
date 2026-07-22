<?php

namespace App\Models;

use App\OperationMaster;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * UserOperation
 *
 * Pivot table assigning a user to an Operation they are allowed to scan
 * for on the Production WIP Scanning screen. A user may be assigned more
 * than one operation.
 *
 * @property int $id
 * @property int $user_id
 * @property int $operation_id
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class UserOperation extends Model
{
    /**
     * @var string
     */
    protected $table = 'user_operations';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'operation_id',
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

        static::creating(function (UserOperation $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (UserOperation $model) {
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
     * The operation this assignment references.
     */
    public function operation()
    {
        return $this->belongsTo(OperationMaster::class, 'operation_id');
    }
}
