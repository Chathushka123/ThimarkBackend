<?php

namespace App\Http\Validators;

class DailyShiftTeamCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'start_date_time' => ['required', 'date'],
      'end_date_time' => ['required', 'date']
    ];
  }
}
