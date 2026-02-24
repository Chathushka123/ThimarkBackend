<?php

namespace App\Http\Validators;

use App\Http\Validators\DailyTeamEmployeeCommonValidator;
use Illuminate\Validation\Rule;

class DailyTeamEmployeeUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], DailyTeamEmployeeCommonValidator::getCommonRules());
  }
}
