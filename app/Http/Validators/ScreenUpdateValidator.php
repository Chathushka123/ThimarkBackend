<?php

namespace App\Http\Validators;

use App\Http\Validators\ScreenCommonValidator;
use Illuminate\Validation\Rule;

class ScreenUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], ScreenCommonValidator::getCommonRules());
  }
}
