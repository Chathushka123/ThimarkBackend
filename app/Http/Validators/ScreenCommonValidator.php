<?php

namespace App\Http\Validators;

class ScreenCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'screen_code' => ['required'],
      'screen_name' => ['required'],
    ];
  }
}



