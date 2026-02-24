<?php

namespace App\Http\Validators;

use App\Http\Validators\ShiftDetailCommonValidator;

class IntegrationLogCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], IntegrationLogCommonValidator::getCommonRules());
  }
}
