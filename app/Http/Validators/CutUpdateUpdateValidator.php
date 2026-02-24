<?php

namespace App\Http\Validators;

use App\Http\Validators\CutUpdateCommonValidator;
use Illuminate\Validation\Rule;

class CutUpdateUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], CutUpdateCommonValidator::getCommonRules());
  }
}
