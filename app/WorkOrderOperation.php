<?php

namespace App\Models;

use App\RoutingOperationMaster;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * WorkOrderOperation
 *
 * @property int $id
 * @property int $work_order_id
 * @property int $routing_operation_master_id
 * @property string $smv
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class WorkOrderOperation extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'work_order_operations';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'work_order_id',
        'routing_operation_master_id',
        'smv',
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

        static::creating(function (WorkOrderOperation $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (WorkOrderOperation $model) {
            $model->updated_by = Auth::id();
        });
    }

    /**
     * The work order this operation belongs to.
     */
    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    /**
     * The routing operation master this record references.
     */
    public function routingOperationMaster()
    {
        return $this->belongsTo(RoutingOperationMaster::class, 'routing_operation_master_id');
    }
}
