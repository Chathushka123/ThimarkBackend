<?php

namespace App\Http\Validators;

use App\Http\Validators\QcRecoverableCommonValidator;
use Illuminate\Validation\Rule;

class QcRecoverableUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], QcRecoverableCommonValidator::getCommonRules());
  }
}
