<?php

namespace App\Http\Validators;

use App\Http\Validators\OcCommonValidator;

class FppoCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'fppo_no' => 'required',
      'qty_json_order' => 'required',
    ], FppoCommonValidator::getCommonRules());
  }
}
