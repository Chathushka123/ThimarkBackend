<?php

namespace App\Http\Validators;

class TeamCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'team_name' => ['required']
    ];
  }
}
