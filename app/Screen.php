<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Screen extends Model
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
        'screen_code',    
        'screen_name' 
           ];

    public function permissions()
    {
        return $this->hasMany(Role::class);
    }
    
}
