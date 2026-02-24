<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Permission extends Model
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
            'user_id' ,
            'screen_id', 
            'role_id' , 
            'grant' , 
           ];

        

        public function user()
        {
            return $this->belongsTo(User::class);
        }
        
        public function role()
        {
            return $this->belongsTo(Role::class);
        }

        public function screen()
        {
            return $this->belongsTo(Screen::class);
        }
    
}
