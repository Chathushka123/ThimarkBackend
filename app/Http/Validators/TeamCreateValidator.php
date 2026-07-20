<?php

namespace App\Http\Validators;

use App\Http\Validators\TeamCommonValidator;

class TeamCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'team_code' => 'required|unique:teams,team_code'
    ], TeamCommonValidator::getCommonRules());
  }
}
