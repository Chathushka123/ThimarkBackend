<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MainModel extends Model
{
    protected $table = 'main_models';

    protected $fillable = [
        'name',
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
}
