<?php

namespace App\Http\Validators;

class FppoCommonValidator
{
  public static function getCommonRules()
  {
    
    return [
      'qty_json' => 'json',
      'utilized' => 'sometimes|boolean'
    ];
  }
}
