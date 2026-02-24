<?php

namespace App\Http\Validators;

use App\Http\Validators\BundleCommonValidator;
use Illuminate\Validation\Rule;

class BundleUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], BundleCommonValidator::getCommonRules());
  }
}
