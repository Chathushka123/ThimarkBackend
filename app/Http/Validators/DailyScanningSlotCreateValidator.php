<?php

namespace App\Http\Validators;

use App\Http\Validators\DailyScanningSlotCommonValidator;

class DailyScanningSlotCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], DailyScanningSlotCommonValidator::getCommonRules());
  }
}
