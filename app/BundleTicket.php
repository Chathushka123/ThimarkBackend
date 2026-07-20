<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * BundleTicket
 *
 * @property int $id
 * @property int $work_order_operation_id
 * @property int $bundle_id
 * @property int $qty
 * @property string $direction
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class BundleTicket extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'bundle_tickets';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'work_order_operation_id',
        'bundle_id',
        'qty',
        'direction',
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

        static::creating(function (BundleTicket $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (BundleTicket $model) {
            $model->updated_by = Auth::id();
        });
    }

    /**
     * The work order operation this ticket belongs to.
     */
    public function workOrderOperation()
    {
        return $this->belongsTo(WorkOrderOperation::class, 'work_order_operation_id');
    }

    /**
     * The bundle this ticket references.
     */
    public function bundle()
    {
        return $this->belongsTo(Bundle::class, 'bundle_id');
    }
}
