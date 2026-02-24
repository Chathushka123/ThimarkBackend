<?php

namespace App\Http\Validators;

use App\Http\Validators\ShiftDetailCommonValidator;

class CartonCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'carton_type' => 'required|unique:cartons'
    ], CartonCommonValidator::getCommonRules());
  }
}
