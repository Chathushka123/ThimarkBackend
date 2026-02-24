<?php

namespace App\Http\Validators;

use App\Http\Validators\TeamCommonValidator;
use Illuminate\Validation\Rule;

class TeamCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'code' => ['required', Rule::unique('teams')]
    ], TeamCommonValidator::getCommonRules());
  }
}
