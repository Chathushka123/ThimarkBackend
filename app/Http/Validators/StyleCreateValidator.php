<?php

namespace App\Http\Validators;

use App\Http\Validators\StyleCommonValidator;
use Illuminate\Validation\Rule;

class StyleCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'style_code' => [
        'required',
        Rule::unique('styles')
      ],
    ], StyleCommonValidator::getCommonRules());
  }
}
