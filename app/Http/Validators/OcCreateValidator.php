<?php

namespace App\Http\Validators;

use App\Http\Validators\OcCommonValidator;

class OcCreateValidator
{
  public static function getCreateRules() {
    return array_merge([
      'wfx_oc_no' => 'required|unique:ocs,wfx_oc_no|max:30'
    ], OcCommonValidator::getCommonRules());
  }
}