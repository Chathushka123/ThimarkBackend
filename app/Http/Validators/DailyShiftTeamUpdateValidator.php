<?php

namespace App\Http\Validators;

use App\Http\Validators\DailyShiftTeamCommonValidator;
use Illuminate\Validation\Rule;

class DailyShiftTeamUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], DailyShiftTeamCommonValidator::getCommonRules());
  }
}
