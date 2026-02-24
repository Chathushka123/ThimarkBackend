<?php

namespace App\Http\Validators;

use App\Http\Validators\ShiftDetailCommonValidator;

class PackingSocConsumptionCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], PackingSocConsumptionCommonValidator::getCommonRules());
  }
}
