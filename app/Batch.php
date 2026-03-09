<?php

namespace App;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    protected $fillable = [
        'model_id',
        'batch_no',
        'qty_json',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'model_id' => 'integer',
        'qty_json' => 'array',
        'active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('active', function ($query) {
            $query->where('active', true);
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
        return $this->belongsTo(\App\Model::class, 'model_id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
