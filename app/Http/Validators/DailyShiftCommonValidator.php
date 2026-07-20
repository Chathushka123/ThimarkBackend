<?php

namespace App\Http\Validators;

class DailyShiftCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'shift_date' => ['required', 'date'],
      'start_date_time' => ['required', 'date'],
      'end_date_time' => ['required', 'date']
    ];
  }
}
