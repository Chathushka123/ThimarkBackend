<?php

namespace App\Http\Validators;

use App\Http\Validators\ShiftDetailCommonValidator;
use Illuminate\Validation\Rule;

class CartonUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], CartonCommonValidator::getCommonRules());
  }
}
