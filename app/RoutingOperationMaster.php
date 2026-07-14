<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * RoutingOperationMaster
 *
 * Pivot table linking a routing (App\Routing) to an operation
 * (App\OperationMaster), with sequence and SMV data. Audit fields
 * (created_by / updated_by) are populated automatically via model events
 * and are intentionally kept out of $fillable so they can never be
 * mass-assigned from a request.
 *
 * @property int $id
 * @property int $routing_id
 * @property int $operation_id
 * @property float $smv
 * @property int $seq
 * @property bool $in
 * @property bool $out
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class RoutingOperationMaster extends Model
{
    /**
     * @var string
     */
    protected $table = 'routing_operation_masters';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'routing_id',
        'operation_id',
        'smv',
        'seq',
        'in',
        'out',
        'active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'routing_id' => 'integer',
        'operation_id' => 'integer',
        'smv' => 'decimal:4',
        'seq' => 'integer',
        'in' => 'boolean',
        'out' => 'boolean',
        'active' => 'boolean',
    ];

    /**
     * Bootstrap model event listeners for audit fields.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (RoutingOperationMaster $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (RoutingOperationMaster $model) {
            $model->updated_by = Auth::id();
        });
    }

    /**
     * The routing this operation belongs to.
     */
    public function routing()
    {
        return $this->belongsTo(Routing::class, 'routing_id');
    }

    /**
     * The operation master this record references.
     */
    public function operation()
    {
        return $this->belongsTo(OperationMaster::class, 'operation_id');
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
