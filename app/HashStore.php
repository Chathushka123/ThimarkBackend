<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class HashStore extends Model
{
    protected $fillable = [
        'key',
        'source'
    ];
}
