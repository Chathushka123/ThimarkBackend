<?php

namespace App;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class MrnDetail extends Model
{
    protected $table = 'mrn_details';

    protected $fillable = [
        'mrn_id',
        'stock_item_id',
        'whl_id',
        'qty',
        'issued_qty',
        'grn_price',
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

    public function mrn()
    {
        return $this->belongsTo(Mrn::class, 'mrn_id');
    }

    public function stockItem()
    {
        return $this->belongsTo(StockMaterial::class, 'stock_item_id');
    }

    public function warehouseLocation()
    {
        return $this->belongsTo(WarehouseLocation::class, 'whl_id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
