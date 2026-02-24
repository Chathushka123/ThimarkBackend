<?php

namespace App\Http\Validators;

use App\Http\Validators\FpoOperationCommonValidator;
use Illuminate\Validation\Rule;

class FpoOperationUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], FpoOperationCommonValidator::getCommonRules());
  }
}
