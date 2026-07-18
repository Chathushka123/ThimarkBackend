<?php

namespace App\Http\Validators;

use App\Http\Validators\DailyShiftCommonValidator;

class DailyShiftUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([
      'shift_id' => ['sometimes', 'required', 'exists:shifts,id']
    ], DailyShiftCommonValidator::getCommonRules());
  }
}
