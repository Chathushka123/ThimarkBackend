<?php

namespace App\Http\Validators;

use App\Http\Validators\CutUpdateCommonValidator;

class CombineOrderCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], CombineOrderCommonValidator::getCommonRules());
  }
}
