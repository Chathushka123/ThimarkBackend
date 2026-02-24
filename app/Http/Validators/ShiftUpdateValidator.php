<?php

namespace App\Http\Validators;

use App\Http\Validators\ShiftCommonValidator;
use Illuminate\Validation\Rule;

class ShiftUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([
      'shift_code' => ['sometimes', 'required', Rule::unique('shifts')->ignore($keyIgnore)]
    ], ShiftCommonValidator::getCommonRules());
  }
}
