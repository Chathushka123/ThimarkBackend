<?php

namespace App\Http\Validators;

use App\Http\Validators\FpoOperationCommonValidator;

class FpoOperationCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'fpo_id' => [
        'required',
        'exists:fpos,id',
        'max:30'
      ],
      'routing_operation_id' => [
        'required',
        'exists:routing_operations,id'
      ]
    ], FpoOperationCommonValidator::getCommonRules());
  }
}
