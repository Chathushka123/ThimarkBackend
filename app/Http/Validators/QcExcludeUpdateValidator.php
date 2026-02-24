<?php

namespace App\Http\Validators;

use App\Http\Validators\QcExcludeCommonValidator;
use Illuminate\Validation\Rule;

class QcExcludeUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], QcExcludeCommonValidator::getCommonRules());
  }
}
