<?php

namespace App\Http\Validators;

use App\Http\Validators\DailyShiftCommonValidator;
use Illuminate\Validation\Rule;

class DailyShiftUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], DailyShiftCommonValidator::getCommonRules());
  }
}
