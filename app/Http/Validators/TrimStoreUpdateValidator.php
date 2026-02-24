<?php

namespace App\Http\Validators;

use App\Http\Validators\TrimStoreCommonValidator;
use Illuminate\Validation\Rule;

class TrimStoreUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], TrimStoreCommonValidator::getCommonRules());
  }
}
