<?php

namespace App\Http\Validators;

use App\Http\Validators\DailyScanningSlotEmployeeCommonValidator;

class DailyScanningSlotEmployeeCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], DailyScanningSlotEmployeeCommonValidator::getCommonRules());
  }
}
