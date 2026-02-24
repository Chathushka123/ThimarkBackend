<?php

namespace App\Http\Validators;

class CombineOrderCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'combine_order_no' => 'required'
    ];
  }
}
