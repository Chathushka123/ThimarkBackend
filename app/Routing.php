<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Routing extends Model
{

    public static function boot()
    {
       parent::boot();
       static::creating(function($model)
       {
           $user = Auth::user();
           $model->created_by_id = $user->id;
           $model->updated_by_id = $user->id;
       });
       static::updating(function($model)
       {
           $user = Auth::user();
           $model->updated_by_id = $user->id;
       });
   }
   
    protected $fillable = [
        'route_code',
        'description'
    ];

    public function routing_operations()
    {
        return $this->hasMany(RoutingOperation::class);
    }

    public function styles()
    {
        return $this->hasMany(Style::class);
    }
}
