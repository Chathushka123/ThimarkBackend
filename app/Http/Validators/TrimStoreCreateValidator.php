<?php

namespace App\Http\Validators;

use App\Http\Validators\TrimStoreCommonValidator;

class TrimStoreCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], TrimStoreCommonValidator::getCommonRules());
  }
}
