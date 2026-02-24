<?php

namespace App\Http\Validators;

use App\Http\Validators\ShiftDetailCommonValidator;

class IntegrationDetailCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], IntegrationDetailCommonValidator::getCommonRules());
  }
}
