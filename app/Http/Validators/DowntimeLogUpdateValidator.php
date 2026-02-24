<?php

namespace App\Http\Validators;

use App\Http\Validators\DowntimeLogCommonValidator;
use Illuminate\Validation\Rule;

class DowntimeLogUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], DowntimeLogCommonValidator::getCommonRules());
  }
}
