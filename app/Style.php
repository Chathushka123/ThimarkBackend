<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Style extends Model
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
        'style_code',
        'description',
        'size_fit',
        'size_fit_json',
        'routing_id'
    ];

    protected $casts = [
        'size_fit' => 'array',
        'size_fit_json' => 'array',
    ];

    public function socs()
    {
        return $this->hasMany(Soc::class);
    }

    public function style_fabrics()
    {
        return $this->hasMany(StyleFabric::class);
    }
    
    public function routing()
    {
        return $this->belongsTo(Routing::class);
    }
}
