<?php

namespace App\Http\Validators;

class RoutingCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'description' => 'required'
    ];
  }
}
