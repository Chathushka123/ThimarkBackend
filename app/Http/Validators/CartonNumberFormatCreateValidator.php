<?php

namespace App\Http\Validators;

use App\Http\Validators\CartonNumberFormatCommonValidator;

class CartonNumberFormatCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], CartonNumberFormatCommonValidator::getCommonRules());
  }
}
