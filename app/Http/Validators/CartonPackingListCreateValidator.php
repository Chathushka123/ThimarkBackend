<?php

namespace App\Http\Validators;

use App\Http\Validators\CartonPackingListCommonValidator;

class CartonPackingListCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], CartonPackingListCommonValidator::getCommonRules());
  }
}
