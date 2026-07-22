<?php

namespace App\Models;

use App\StockMaterial;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Bundle
 *
 * @property int $id
 * @property int $work_order_id
 * @property int $stock_material_id
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class Bundle extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'bundles';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'work_order_id',
        'stock_material_id',
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

        static::creating(function (Bundle $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (Bundle $model) {
            $model->updated_by = Auth::id();
        });
    }

    /**
     * The work order this bundle belongs to.
     */
    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    /**
     * The stock material this bundle references.
     */
    public function stockMaterial()
    {
        return $this->belongsTo(StockMaterial::class, 'stock_material_id');
    }

    /**
     * The line-item details (qty/size breakdown) for this bundle.
     */
    public function bundleDetails()
    {
        return $this->hasMany(BundleDetail::class, 'bundle_id');
    }
}
