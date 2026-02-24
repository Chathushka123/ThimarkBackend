<?php

namespace App\Http\Validators;

use App\Http\Validators\RecoverableScanCommonValidator;
use Illuminate\Validation\Rule;

class RecoverableScanUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], RecoverableScanCommonValidator::getCommonRules());
  }
}
