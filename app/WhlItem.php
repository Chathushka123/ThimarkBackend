<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WhlItem extends Model
{
    protected $table = 'whl_items';

    protected $fillable = [
        'whl_id',
        'stock_item_id',
        'qty',
        'active',
        'created_by',
        'updated_by',
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

    public function warehouseLocation()
    {
        return $this->belongsTo(WarehouseLocation::class, 'whl_id');
    }

    public function stockItem()
    {
        return $this->belongsTo(StockMaterial::class, 'stock_item_id');
    }
}
