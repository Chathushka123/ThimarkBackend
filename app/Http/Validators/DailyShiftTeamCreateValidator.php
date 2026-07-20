<?php

namespace App\Http\Validators;

use App\Http\Validators\DailyShiftTeamCommonValidator;

class DailyShiftTeamCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'daily_shift_id' => 'required|exists:daily_shifts,id',
      'team_id' => 'required|exists:teams,id'
    ], DailyShiftTeamCommonValidator::getCommonRules());
  }
}
