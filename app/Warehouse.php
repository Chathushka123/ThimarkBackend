<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $table = 'warehouses';

    protected $fillable = [
        'name',
        'code',
        'location_basis',
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

    public function locations()
    {
        return $this->hasMany(WarehouseLocation::class);
    }
}

class WarehouseLocation extends Model
{
    protected $table = 'warehouse_locations';

    protected $fillable = [
        'warehouse_id',
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
}
