<?php

namespace App\Http\Validators;

use App\Http\Validators\RoutingCommonValidator;
use Illuminate\Validation\Rule;

class RoutingUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([
      'route_code' => [
        'required',
        Rule::unique('routings')->ignore($keyIgnore)
      ]
    ], RoutingCommonValidator::getCommonRules());
  }
}
