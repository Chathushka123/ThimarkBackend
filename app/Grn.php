<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class Grn extends Model
{
    protected $table = 'grns';

    protected $fillable = [
        'created_by',
        'rmpono',
        'remark',
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
            }
        });
    }


    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
