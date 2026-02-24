<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Test extends Model
{
    protected $fillable = [
        'code',
        'name'
    ];

    public function test_cartons()
    {
        return $this->hasMany(TestCarton::class);
    }

}