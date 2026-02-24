<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ForeignKeyMapper extends Model
{
    protected $fillable = [
        'model',
        'key_mapping'
    ];

    protected $casts = [
        'key_mapping' => 'json'
    ];
}
