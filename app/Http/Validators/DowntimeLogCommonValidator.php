<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class DowntimeLogCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'downtime_definition_id' => ['required', 'exists:downtime_definitions,id'],
      'daily_scanning_slot_id' => ['required', 'exists:daily_scanning_slots,id'],
      'daily_shift_team_id' => ['required', 'exists:daily_shift_teams,id'],
      'downtime_minutes' => ['required', 'numeric'],
      'reason' => ['required']
    ];
  }
}
