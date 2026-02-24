<?php

namespace App\Http\Validators;

use App\Http\Validators\FpoCommonValidator;

class FpoCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'wfx_fpo_no' => [
        'required',
        'unique:fpos,wfx_fpo_no',
        'max:30'
      ],
      'soc_id' => [
        'required',
        'exists:socs,id'
      ]
    ], FpoCommonValidator::getCommonRules());
  }
}
