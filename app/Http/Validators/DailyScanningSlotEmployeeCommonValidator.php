<?php

namespace App\Http\Validators;

class DailyScanningSlotEmployeeCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'employee_id' => ['required', 'exists:employees,id'],
      'daily_scanning_slot_id' => ['required', 'exists:daily_scanning_slots,id'],
      'daily_shift_team_id' => ['required', 'required', 'exists:daily_shift_teams,id'],
    ];
  }
}
