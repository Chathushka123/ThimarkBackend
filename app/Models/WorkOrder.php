<?php

namespace App\Models;

use App\BatchDetail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * WorkOrder
 *
 * @property int $id
 * @property int $batch_detail_id
 * @property string $status
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class WorkOrder extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'work_orders';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'batch_detail_id',
        'status',
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

        static::creating(function (WorkOrder $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (WorkOrder $model) {
            $model->updated_by = Auth::id();
        });
    }

    /**
     * The batch detail this work order belongs to.
     */
    public function batchDetail()
    {
        return $this->belongsTo(BatchDetail::class, 'batch_detail_id');
    }
}
