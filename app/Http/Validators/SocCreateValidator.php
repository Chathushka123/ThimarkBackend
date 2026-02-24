<?php

namespace App\Http\Validators;

use App\Http\Validators\SocCommonValidator;

class SocCreateValidator
{
  public static function getCreateRules() {
    return array_merge([
      'wfx_soc_no' => 'required|unique:socs,wfx_soc_no|max:100'
    ], SocCommonValidator::getCommonRules());
  }
}