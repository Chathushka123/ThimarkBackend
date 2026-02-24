<?php

namespace App\Http\Validators;

use App\Http\Validators\PackingListCommonValidator;
use Illuminate\Validation\Rule;

class PackingListUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], PackingListCommonValidator::getCommonRules());
  }
}
