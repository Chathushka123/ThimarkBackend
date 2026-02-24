<?php

namespace App\Http\Validators;

use App\Http\Validators\ScreenCommonValidator;

class ScreenCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], ScreenCommonValidator::getCommonRules());
  }
}
