<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class Mrn extends Model
{
    protected $fillable = [
        'batch_id',
        'warehouse_id',
        'status',
        'issued_to',
        'active',
        'created_by',
        'updated_by',
        'finalized_at',
        'complete_at',
    ];

    // Allowed status values: open, finalized, proccesing, complete

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

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

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function issuedTo()
    {
        return $this->belongsTo(User::class, 'issued_to');
    }

    public function details()
    {
        return $this->hasMany(MrnDetail::class, 'mrn_id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
