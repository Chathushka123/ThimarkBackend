<?php

namespace App\Http\Validators;

use App\Http\Validators\BuyerCommonValidator;

class BuyerCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'buyer_code' => 'required|unique:buyers'
    ], BuyerCommonValidator::getCommonRules());
  }
}
