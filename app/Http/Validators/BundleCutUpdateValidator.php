<?php

namespace App\Http\Validators;

use App\Http\Validators\BundleCutCommonValidator;
use Illuminate\Validation\Rule;

class BundleCutUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], BundleCutCommonValidator::getCommonRules());
  }
}
