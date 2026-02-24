<?php

namespace App\Http\Validators;

use App\Http\Validators\LaySheetCommonValidator;
use Illuminate\Validation\Rule;

class LaySheetUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], LaySheetCommonValidator::getCommonRules());
  }
}
