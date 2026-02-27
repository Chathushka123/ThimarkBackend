<?php

namespace App;

use Illuminate\Database\Eloquent\Model as EloquentModel;

class Model extends EloquentModel
{
    protected $table = 'models';

    protected $fillable = [
        'main_model_id',
        'color',
        'sizes',
        'name',
        'active',
    ];

    protected $casts = [
        'sizes' => 'array',
        'active' => 'boolean',
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

    public function mainModel()
    {
        return $this->belongsTo(MainModel::class, 'main_model_id');
    }

    public function modelStockItems()
    {
        return $this->hasMany(ModelStockItem::class, 'model_id');
    }
}
