<?php

namespace App\Http\Validators;

class RoutingOperationCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'description' => ['required'],
      'smv' => ['required', 'sometimes', 'numeric'],
      'routing_id' => ['required', 'sometimes']
    ];
  }
}
