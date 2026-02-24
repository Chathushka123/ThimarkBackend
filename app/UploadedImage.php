<?php

namespace App;


use Illuminate\Database\Eloquent\Model;

class UploadedImage extends Model
{

    protected $fillable = ['filename', 'mime_type', 'data', 'invoice_id'];
    public $timestamps = true;
}
