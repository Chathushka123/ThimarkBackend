<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockMaterial extends Model
{
    protected $table = 'stock_materials';

    protected $fillable = [
        'name',
        'code',
        'supplier',
        'lead_time',
        'min_qty',
        'size',
        'unit_price',
        'uom_id',
        'category',
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
    }

    protected $casts = [
        'size' => 'array',
    ];

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function warehouseLocations()
    {
        return $this->hasMany(WarehouseLocation::class, 'stock_item_id');
    }
}
