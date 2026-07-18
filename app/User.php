<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
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
        'name', 
        'email', 
        'password', 
        'role_id',
        'is_active',
        'common_user',
        'common_user_state',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
         'password',
         'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function bundle_bins()
    {
        return $this->hasMany(BundleBin::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
