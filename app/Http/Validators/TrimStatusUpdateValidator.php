<?php

namespace App\Http\Validators;

use App\Http\Validators\TrimStatusCommonValidator;
use Illuminate\Validation\Rule;

class TrimStatusUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], TrimStatusCommonValidator::getCommonRules());
  }
}
