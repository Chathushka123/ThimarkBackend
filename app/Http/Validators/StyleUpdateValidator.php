<?php

namespace App\Http\Validators;

use App\Http\Validators\StyleCommonValidator;
use Illuminate\Validation\Rule;

class StyleUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([
      'style_code' => [
        'required',
        Rule::unique('styles')->ignore($keyIgnore)
      ]
    ], StyleCommonValidator::getCommonRules());
  }
}
