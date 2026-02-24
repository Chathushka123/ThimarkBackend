<?php

namespace App\Http\Validators;

use App\Http\Validators\DailyTeamEmployeeCommonValidator;

class DailyTeamEmployeeCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], DailyTeamEmployeeCommonValidator::getCommonRules());
  }
}
