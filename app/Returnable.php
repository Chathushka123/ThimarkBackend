<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class Returnable extends Model
{
    protected $fillable = [
        'issued_to',
        'total_qty',
        'issued_qty',
        'return_qty',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_qty' => 'double',
        'issued_qty' => 'double',
        'return_qty' => 'double',
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

    public function issuedTo()
    {
        return $this->belongsTo(User::class, 'issued_to');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
