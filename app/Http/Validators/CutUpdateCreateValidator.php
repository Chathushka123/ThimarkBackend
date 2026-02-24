<?php

namespace App\Http\Validators;

use App\Http\Validators\CutUpdateCommonValidator;

class CutUpdateCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], CutUpdateCommonValidator::getCommonRules());
  }
}
