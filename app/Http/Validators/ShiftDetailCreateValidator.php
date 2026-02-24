<?php

namespace App\Http\Validators;

use App\Http\Validators\ShiftDetailCommonValidator;

class ShiftDetailCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], ShiftDetailCommonValidator::getCommonRules());
  }
}
