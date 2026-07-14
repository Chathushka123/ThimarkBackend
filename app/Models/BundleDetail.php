<?php

namespace App\Models;

use App\StockMaterial;
use App\WarehouseLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * BundleDetail
 *
 * @property int $id
 * @property int $bundle_id
 * @property int $stock_material_id
 * @property int|null $whl_id
 * @property int $qty
 * @property string|null $size
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class BundleDetail extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'bundle_details';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'bundle_id',
        'stock_material_id',
        'whl_id',
        'qty',
        'size',
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

        static::creating(function (BundleDetail $model) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        });

        static::updating(function (BundleDetail $model) {
            $model->updated_by = Auth::id();
        });
    }

    /**
     * The bundle this detail belongs to.
     */
    public function bundle()
    {
        return $this->belongsTo(Bundle::class, 'bundle_id');
    }

    /**
     * The stock material this detail references.
     */
    public function stockMaterial()
    {
        return $this->belongsTo(StockMaterial::class, 'stock_material_id');
    }

    /**
     * The warehouse location this detail references.
     */
    public function warehouseLocation()
    {
        return $this->belongsTo(WarehouseLocation::class, 'whl_id');
    }
}
