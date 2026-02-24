<?php

namespace App\Http\Validators;

class FpoOperationCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'print_bundle' => 'required',
      'wip_point' => 'required'
    ];
  }
}
