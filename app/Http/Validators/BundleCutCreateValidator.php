<?php

namespace App\Http\Validators;

use App\Http\Validators\BundleCutCommonValidator;

class BundleCutCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], BundleCutCommonValidator::getCommonRules());
  }
}
