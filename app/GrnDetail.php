<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GrnDetail extends Model
{
    protected $table = 'grn_details';

    protected $fillable = [
        'whl_item_id',
        'qty',
        'grn_price',
        'created_by',
        'updated_by',
        'active',
    ];

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('active', function ($query) {
            $query->where('active', true);
        });
        static::deleting(function ($model) {
            $model->active = false;
            $model->save();
            return false;
        });
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
                $model->updated_by = auth()->id();
            }
        });
        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }


    public function whlItem()
    {
        return $this->belongsTo(WhlItem::class, 'whl_item_id');
    }
}
