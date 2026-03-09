<?php

namespace App;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class ModelStockItem extends EloquentModel
{
    protected $table = 'model_stock_items';

    protected $fillable = [
        'stock_item_id',
        'model_id',
        'consumption',
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

    public function model()
    {
        return $this->belongsTo(Model::class, 'model_id');
    }

    public function stockItem()
    {
        return $this->belongsTo(StockMaterial::class, 'stock_item_id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
