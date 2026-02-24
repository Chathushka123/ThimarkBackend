<?php

namespace App\Http\Validators;

use App\Http\Validators\RoutingOperationCommonValidator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class RoutingOperationUpdateValidator
{
  public static function getUpdateRules($keyIgnore, $rec)
  {
    return array_merge([
      //'operation_code' => ['required', Rule::unique('routing_operations')->ignore($keyIgnore) ] 
      'operation_code' => ['required', Rule::unique('routing_operations')->where(function ($query) use ($rec) {
        return $query->where('routing_id', $rec['routing_id']);      
      })->ignore($keyIgnore)],
         
    ], RoutingOperationCommonValidator::getCommonRules());
  }
}
