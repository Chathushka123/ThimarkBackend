<?php

namespace App\Http\Validators;

use App\Http\Validators\RoutingCommonValidator;

class RoutingCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(['route_code' => 'required|unique:routings',], RoutingCommonValidator::getCommonRules());
  }
}
