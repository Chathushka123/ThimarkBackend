<?php

namespace App\Http\Validators;

use App\Http\Validators\DailyShiftCommonValidator;

class DailyShiftCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'shift_id' => 'required|exists:shifts,id'
    ], DailyShiftCommonValidator::getCommonRules());
  }
}
