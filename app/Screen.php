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
           $model->created_by_id = Auth::id();
           $model->updated_by_id = Auth::id();
       });
       static::updating(function($model)
       {
           $model->updated_by_id = Auth::id();
       });
   }
   
    protected $fillable = [
        'screen_code',    
        'screen_name' 
           ];

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }
    
}
