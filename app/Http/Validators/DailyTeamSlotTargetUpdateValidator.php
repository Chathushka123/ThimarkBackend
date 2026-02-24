<?php

namespace App\Http\Validators;

use App\Http\Validators\DailyTeamSlotTargetCommonValidator;
use Illuminate\Validation\Rule;

class DailyTeamSlotTargetUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], DailyTeamSlotTargetCommonValidator::getCommonRules());
  }
}
