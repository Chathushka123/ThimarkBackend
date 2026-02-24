<?php

namespace App\Http\Validators;

use App\Http\Validators\QcExcludeCommonValidator;

class QcExcludeCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], QcExcludeCommonValidator::getCommonRules());
  }
}
