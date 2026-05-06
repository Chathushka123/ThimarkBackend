<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class GrnDetail extends Model
{
    protected $table = 'grn_details';

    protected $fillable = [
        'grn_id',
        'warehouse_location_id',
        'stock_item_id',
        'whl_item_id',
        'qty',
        'available_qty',
        'grn_price',
        'created_by',
        'updated_by',
        'active',
    ];

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('active', function ($query) {
            $query->where('active', true);
        });
        static::deleting(function ($model) {
            $model->active = false;
            $model->save();
            return false;
        });
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
                $model->updated_by = auth()->id();
            }
        });
        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }


    public function grn()
    {
        return $this->belongsTo(Grn::class, 'grn_id');
    }

    public function warehouseLocation()
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function stockMaterial()
    {
        return $this->belongsTo(StockMaterial::class, 'stock_item_id');
    }

    public function whlItem()
    {
        return $this->belongsTo(WhlItem::class, 'whl_item_id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
