<?php

namespace App;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    protected $table = 'inventory_logs';

    public $timestamps = false;

    protected $fillable = [
        'wh_id',
        'bin_id',
        'stock_material_id',
        'whl_item_id',
        'log_type',
        'previous_qty',
        'new_qty',
        'old_bin',
        'new_bin',
        'old_material',
        'new_material',
        'updated_by',
        'updated_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
            $model->updated_at = now();
        });
        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
            $model->updated_at = now();
        });
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'wh_id');
    }

    public function bin()
    {
        return $this->belongsTo(WarehouseLocation::class, 'bin_id');
    }

    public function stockMaterial()
    {
        return $this->belongsTo(StockMaterial::class, 'stock_material_id');
    }

    public function whlItem()
    {
        return $this->belongsTo(WhlItem::class, 'whl_item_id');
    }

    public function oldBinLocation()
    {
        return $this->belongsTo(WarehouseLocation::class, 'old_bin');
    }

    public function newBinLocation()
    {
        return $this->belongsTo(WarehouseLocation::class, 'new_bin');
    }

    public function oldMaterial()
    {
        return $this->belongsTo(StockMaterial::class, 'old_material');
    }

    public function newMaterial()
    {
        return $this->belongsTo(StockMaterial::class, 'new_material');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
