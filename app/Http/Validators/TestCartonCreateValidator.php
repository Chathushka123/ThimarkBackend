<?php

namespace App\Http\Validators;

use App\Http\Validators\ShiftDetailCommonValidator;

class TestCartonCreateValidator
{
  public static function getCreateRules()
  {
   // return array_merge([], BuyerCartonCommonValidator::getCommonRules());
   return [ 
    'test_id' => ['numeric','required','exists:tests,id'], 
    'carton_id' => ['numeric','required','exists:cartons,id']
    ];
  }
}