<?php

namespace App\Http\Validators;

use App\Http\Validators\ScreenCommonValidator;
use Illuminate\Validation\Rule;

class ScreenUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge(ScreenCommonValidator::getCommonRules(), [
      'screen_code' => ['sometimes', 'required', Rule::unique('screens', 'screen_code')->ignore($keyIgnore)],
      'screen_name' => ['sometimes', 'required', Rule::unique('screens', 'screen_name')->ignore($keyIgnore)],
    ]);
  }
}
