<?php

namespace App\Http\Validators;

class DailyShiftTeamCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'current_date' => ['sometimes', 'date'],
      'team_id' => ['required', 'exists:teams,id'],
      'daily_shift_id' => ['required', 'exists:daily_shifts,id'],
      'start_date_time' => ['required'],
      'end_date_time' => ['required'],
      // 'break' => ['sometimes', 'required', 'numeric'],
      // 'frequency' => ['sometimes', 'required', 'numeric'],
      // 'total_target' => ['nullable', 'required', 'numeric']
    ];
  }
}
