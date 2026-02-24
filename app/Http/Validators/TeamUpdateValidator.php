<?php

namespace App\Http\Validators;

use App\Http\Validators\TeamCommonValidator;
use Illuminate\Validation\Rule;

class TeamUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([
      'code' => ['required', Rule::unique('teams')->ignore($keyIgnore)]
    ], TeamCommonValidator::getCommonRules());
  }
}
