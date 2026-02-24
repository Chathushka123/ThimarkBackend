<?php

namespace App\Http\Validators;

use App\Http\Validators\JobCardCommonValidator;

class JobCardCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], JobCardCommonValidator::getCommonRules());
  }
}
