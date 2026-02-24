<?php

namespace App\Http\Validators;

class TeamCategoryCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'description' => ['required']
    ];
  }
}
