<?php

namespace App;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class WarehouseLocation extends Model
{
    protected $table = 'warehouse_locations';

    protected $fillable = [
        'warehouse_id',
        'stock_item_id',
        'rack',
        'bin',
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

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockMaterial()
    {
        return $this->belongsTo(StockMaterial::class, 'stock_item_id');
    }

    public function whlItems()
    {
        return $this->hasMany(WhlItem::class, 'whl_id');
    }
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
