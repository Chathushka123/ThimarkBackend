<?php

namespace App\Http\Validators;

use App\Http\Validators\RecoverableScanCommonValidator;

class RecoverableScanCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], RecoverableScanCommonValidator::getCommonRules());
  }
}
