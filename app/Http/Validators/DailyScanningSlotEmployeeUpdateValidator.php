<?php

namespace App\Http\Validators;

use App\Http\Validators\DailyScanningSlotEmployeeCommonValidator;
use Illuminate\Validation\Rule;

class DailyScanningSlotEmployeeUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], DailyScanningSlotEmployeeCommonValidator::getCommonRules());
  }
}
