<?php

namespace App\Http\Validators;

use App\Http\Validators\FpoCommonValidator;
use Illuminate\Validation\Rule;

class FpoUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([
      'wfx_fpo_no' => [
        'sometimes',
        'required',
        Rule::unique('fpos')->ignore($keyIgnore),
        'max:30'
      ],
      'utilized' => 'sometimes|boolean'
    ], FpoCommonValidator::getCommonRules());
  }
}
