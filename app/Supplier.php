<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Supplier extends Model
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
        'contact_no',
        'email'
    ];

    public function purchaseOrders()
{
    return $this->hasMany(PurchaseOrder::class);
}


}
