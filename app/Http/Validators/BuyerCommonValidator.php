<?php

namespace App\Http\Validators;

class BuyerCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'name' => 'required'
    ];
  }
}
