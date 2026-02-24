<?php

namespace App\Http\Validators;

use App\Http\Validators\TrimStatusCommonValidator;

class TrimStatusCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], TrimStatusCommonValidator::getCommonRules());
  }
}
