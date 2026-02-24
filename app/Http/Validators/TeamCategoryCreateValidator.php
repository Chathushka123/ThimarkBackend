<?php

namespace App\Http\Validators;

use App\Http\Validators\TeamCategoryCommonValidator;

class TeamCategoryCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'code' => ['sometimes', 'required', 'unique:team_categories,code']
    ], TeamCategoryCommonValidator::getCommonRules());
  }
}
