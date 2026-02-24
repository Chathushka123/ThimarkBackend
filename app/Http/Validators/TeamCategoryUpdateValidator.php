<?php

namespace App\Http\Validators;

use App\Http\Validators\TeamCategoryCommonValidator;
use Illuminate\Validation\Rule;

class TeamCategoryUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([
      'code' => ['sometimes', 'required', Rule::unique('team_categories')->ignore($keyIgnore)]
    ], TeamCategoryCommonValidator::getCommonRules());
  }
}
