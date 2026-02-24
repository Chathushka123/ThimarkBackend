<?php

namespace App\Http\Validators;

class ShiftCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => ['required'],
      'duration' => ['required']
    ];
  }
}
