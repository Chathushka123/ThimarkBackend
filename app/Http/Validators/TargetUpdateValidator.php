<?php

namespace App\Http\Validators;

use App\Http\Validators\TargetCommonValidator;
use Illuminate\Validation\Rule;

class TargetUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], TargetCommonValidator::getCommonRules());
  }
}
