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
        'stock_item_id',
        'remarks',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_qty' => 'double',
        'issued_qty' => 'double',
        'return_qty' => 'double',
        'stock_item_id' => 'integer',
        'active' => 'boolean',
    ];
    public function stockItem()
    {
        return $this->belongsTo(StockMaterial::class, 'stock_item_id');
    }

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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
