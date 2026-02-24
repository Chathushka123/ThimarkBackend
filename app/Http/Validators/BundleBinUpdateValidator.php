<?php

namespace App\Http\Validators;

use App\Http\Validators\BundleBinCommonValidator;
use Illuminate\Validation\Rule;

class BundleBinUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], BundleBinCommonValidator::getCommonRules());
  }
}
