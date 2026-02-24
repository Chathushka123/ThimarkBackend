<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TestCarton extends Model
{
    protected $fillable = [
            'test_id' , 
            'carton_id' , 
           ];

    protected $table = 'test_carton';

    public function test()
    {
        return $this->belongsTo(Test::class);
    }
    public function carton()
    {
        return $this->belongsTo(Carton::class);
    }
    
}