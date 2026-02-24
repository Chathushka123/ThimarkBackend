<?php

namespace App\Http\Validators;

use App\Http\Validators\DailyShiftCommonValidator;

class DailyShiftCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], DailyShiftCommonValidator::getCommonRules());
  }
}
