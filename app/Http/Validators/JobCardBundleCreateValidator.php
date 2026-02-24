<?php

namespace App\Http\Validators;

use App\Http\Validators\JobCardBundleCommonValidator;

class JobCardBundleCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], JobCardBundleCommonValidator::getCommonRules());
  }
}
