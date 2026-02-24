<?php

namespace App\Http\Validators;

use App\Http\Validators\BundleCommonValidator;

class BundleCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], BundleCommonValidator::getCommonRules());
  }
}
