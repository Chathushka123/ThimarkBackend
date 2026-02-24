<?php

namespace App\Http\Validators;

use App\Http\Validators\ShiftDetailCommonValidator;

class BuyerCartonCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], BuyerCartonCommonValidator::getCommonRules());
  }
}
