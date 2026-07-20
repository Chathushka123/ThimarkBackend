<?php

namespace App\Http\Validators;

class ShiftCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'shift_name' => ['required']
    ];
  }
}
