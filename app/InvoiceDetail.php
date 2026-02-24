<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class InvoiceDetail extends Model
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
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'active',
        'created_by',
        'updated_by',
    ];



    public function invoices()
    {
        return $this->belongsTo(Invoice::class);
    }


    public function invoice_details()
    {
        return $this->hasMany(InvoiceDetail::class);
    }


}
