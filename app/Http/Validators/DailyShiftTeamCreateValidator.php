<?php

namespace App\Http\Validators;

use App\Http\Validators\DailyShiftTeamCommonValidator;

class DailyShiftTeamCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], DailyShiftTeamCommonValidator::getCommonRules());
  }
}
