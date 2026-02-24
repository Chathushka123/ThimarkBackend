<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Transaction extends Model
{
    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $user = Auth::user();
            $model->created_by = $user->id;
            $model->updated_by = $user->id;
        });
        static::updating(function ($model) {
            $user = Auth::user();
            $model->updated_by = $user->id;
        });
    }

    protected $fillable = [
        'invoice_id',
        'amount',
        'payee',
        'description',
        'transaction_type',
        'payment_method',
        'active',
        'payment_date',
        'created_by',
        'updated_by'
    ];



    public function invoices()
    {
        return $this->belongsTo(Invoice::class);
    }
}
