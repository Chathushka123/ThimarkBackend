<?php

namespace App\Http\Validators;

use App\Http\Validators\RoutingOperationCommonValidator;
use Illuminate\Validation\Rule;

class RoutingOperationCreateValidator
{
  public static function getCreateRules($rec)
  {
    return array_merge([
      'operation_code' => ['required', Rule::unique('routing_operations')->where(function ($query) use ($rec) {
        return $query->where('routing_id', $rec['routing_id']);
      
      })],
      'parallel_operation_no' => ['required'],
      'shop_floor_seq' => ['required']
    ], RoutingOperationCommonValidator::getCommonRules());
  }
}


