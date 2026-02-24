<?php

namespace App\Http\Validators;

use App\Http\Validators\JobCardCommonValidator;
use Illuminate\Validation\Rule;

class JobCardUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], JobCardCommonValidator::getCommonRules());
  }
}
