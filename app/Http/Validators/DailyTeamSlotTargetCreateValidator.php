<?php

namespace App\Http\Validators;

use App\Http\Validators\DailyTeamSlotTargetCommonValidator;

class DailyTeamSlotTargetCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'daily_scanning_slot_id' => 'required|exists:daily_scanning_slots,id',
      'daily_shift_team_id' => 'required|exists:daily_shift_teams,id'
    ], DailyTeamSlotTargetCommonValidator::getCommonRules());
  }
}
