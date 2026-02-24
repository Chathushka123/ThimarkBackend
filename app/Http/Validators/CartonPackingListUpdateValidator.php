<?php

namespace App\Http\Validators;

use App\Http\Validators\CartonPackingListCommonValidator;
use Illuminate\Validation\Rule;

class CartonPackingListUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], CartonPackingListCommonValidator::getCommonRules());
  }
}
