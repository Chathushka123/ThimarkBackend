<?php

namespace App\Http\Validators;

class ScreenCommonValidator
{
  public static function getCommonRules()
  {
    return ['screen_code' => 'required|unique:screens',
    'screen_name' => 'required|unique:screens' ];
  }
}



