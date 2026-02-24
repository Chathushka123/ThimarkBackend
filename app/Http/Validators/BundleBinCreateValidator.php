<?php

namespace App\Http\Validators;

use App\Http\Validators\BundleBinCommonValidator;
use Illuminate\Validation\Rule;

class BundleBinCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], BundleBinCommonValidator::getCommonRules());
  }
}
