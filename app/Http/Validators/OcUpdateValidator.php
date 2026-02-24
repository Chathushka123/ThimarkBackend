<?php

namespace App\Http\Validators;

use App\Http\Validators\OcCommonValidator;
use Illuminate\Validation\Rule;

class OcUpdateValidator
{
  public static function getUpdateRules($keyIgnore) {
    return array_merge([
      'wfx_oc_no' => [
        'sometimes',
        'required',
        Rule::unique('ocs')->ignore($keyIgnore),
        'max:30'
      ]
    ], OcCommonValidator::getCommonRules());
  }
}