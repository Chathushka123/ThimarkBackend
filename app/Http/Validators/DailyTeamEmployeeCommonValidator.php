<?php

namespace App\Http\Validators;

class DailyTeamEmployeeCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'daily_shift_team_id' => ['required', 'exists:daily_shift_teams,id'],
      'employee_id' => ['required', 'exists:employees,id']
    ];
  }
}
