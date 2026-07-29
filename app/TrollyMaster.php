<?php

namespace App;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class TrollyMaster extends Model
{
    protected $table = 'trolly_masters';

    protected $fillable = [
        'code',
        'name',
        'active',
        'used',
    ];

    protected $casts = [
        'active' => 'boolean',
        'used' => 'boolean',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
