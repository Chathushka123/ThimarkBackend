<?php

namespace App\Http\Validators;

use App\Http\Validators\JobCardBundleCommonValidator;
use Illuminate\Validation\Rule;

class JobCardBundleUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], JobCardBundleCommonValidator::getCommonRules());
  }
}
