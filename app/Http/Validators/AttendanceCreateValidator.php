<?php

namespace App\Http\Validators;

use App\Http\Validators\AttendanceCommonValidator;

class AttendanceCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], AttendanceCommonValidator::getCommonRules());
  }
}
