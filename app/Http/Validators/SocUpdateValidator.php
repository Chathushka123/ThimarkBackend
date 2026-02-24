<?php

namespace App\Http\Validators;

use App\Http\Validators\SocCommonValidator;
use Illuminate\Validation\Rule;

class SocUpdateValidator
{
  public static function getUpdateRules($keyIgnore) {
    return array_merge([
      'wfx_soc_no' => [
        'sometimes',
        'required',
        Rule::unique('socs')->ignore($keyIgnore),
        'max:100'
      ]
    ], SocCommonValidator::getCommonRules());
  }
}