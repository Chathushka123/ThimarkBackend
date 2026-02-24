<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Invoice extends Model
{
    public static function boot()
    {
       parent::boot();
       static::creating(function($model)
       {
           $user = Auth::user();
           $model->created_by = $user->id;
           $model->updated_by = $user->id;
       });
       static::updating(function($model)
       {
           $user = Auth::user();
           $model->updated_by = $user->id;
       });
   }
   
    protected $fillable = [
        'name',
        'address',
        'mobile',
        'landline',
        'status',
        'amount',
        'paid_amount',
        'due_amount',
        'invoice_date',
        'due_date',
        'active',
        'status_id',
        'created_by',
        'updated_by',
    ];



    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }


    public function invoice_details()
    {
        return $this->hasMany(InvoiceDetail::class);
    }
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

        public function invoice_status()
    {
        return $this->belongsTo(InvoiceStatus::class, 'status_id');
    }

}
