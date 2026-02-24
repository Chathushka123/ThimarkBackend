<?php

namespace App\Http\Validators;

use App\Http\Validators\ShiftDetailCommonValidator;
use Illuminate\Validation\Rule;

class BuyerCartonUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], BuyerCartonCommonValidator::getCommonRules());
  }
}
