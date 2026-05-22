<?php

namespace App;

use App\Model as AppModel;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class BatchDetail extends EloquentModel
{
    protected $table = 'batch_details';

    protected $fillable = [
        'batch_id',
        'model_id',
        'quantity',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'batch_id' => 'integer',
        'model_id' => 'integer',
        'quantity' => 'integer',
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

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function model()
    {
        return $this->belongsTo(AppModel::class, 'model_id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
