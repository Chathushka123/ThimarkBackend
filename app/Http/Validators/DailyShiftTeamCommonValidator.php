<?php

namespace App\Http\Validators;

class DailyShiftTeamCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'start_date_time' => ['required', 'date'],
      'end_date_time' => ['required', 'date'],
      'no_of_operators' => ['nullable', 'integer', 'min:0']
    ];
  }
}
