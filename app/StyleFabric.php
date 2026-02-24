<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class StyleFabric extends Model
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
        'fabric',
        'style_id'
    ];

  
    public function style()
    {
        return $this->belongsTo(Style::class);
    }

    public function fpo_fabrics()
    {
        return $this->hasMany(FpoFabric::class);
    }
}
