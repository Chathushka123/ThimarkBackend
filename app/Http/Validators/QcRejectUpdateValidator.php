<?php

namespace App\Http\Validators;

use App\Http\Validators\QcRejectCommonValidator;
use Illuminate\Validation\Rule;

class QcRejectUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], QcRejectCommonValidator::getCommonRules());
  }
}
