<?php

namespace App\Http\Validators;

use App\Http\Validators\ShiftDetailCommonValidator;
use Illuminate\Validation\Rule;

class PackingListDetailUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], PackingListDetailCommonValidator::getCommonRules());
  }
}
