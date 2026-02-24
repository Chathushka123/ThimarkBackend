<?php

namespace App\Http\Validators;

use App\Http\Validators\AttendanceCommonValidator;
use Illuminate\Validation\Rule;

class AttendanceUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], AttendanceCommonValidator::getCommonRules());
  }
}
