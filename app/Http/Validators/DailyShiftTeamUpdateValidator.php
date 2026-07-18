<?php

namespace App\Http\Validators;

use App\Http\Validators\DailyShiftTeamCommonValidator;

class DailyShiftTeamUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([
      'daily_shift_id' => ['sometimes', 'required', 'exists:daily_shifts,id'],
      'team_id' => ['sometimes', 'required', 'exists:teams,id']
    ], DailyShiftTeamCommonValidator::getCommonRules());
  }
}
