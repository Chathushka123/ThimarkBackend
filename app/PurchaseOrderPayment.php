<?php

namespace App;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderPayment extends Model
{
    protected $table = 'purchase_order_payments';

    protected $fillable = [
        'purchase_order_id',
        'amount',
        'payment_date',
        'note',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
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

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
