<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Role extends Model
{
    public static function boot()
    {
       parent::boot();
       static::creating(function($model)
       {
           $model->created_by_id = Auth::id();
           $model->updated_by_id = Auth::id();
       });
       static::updating(function($model)
       {
           $model->updated_by_id = Auth::id();
       });
   }
   
    protected $fillable = [
            'role_code' , 
               'description' , 
           ];

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
