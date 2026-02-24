<?php

namespace App\Http\Validators;

use App\Http\Validators\TargetCommonValidator;
use Illuminate\Validation\Rule;

class TargetCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], TargetCommonValidator::getCommonRules());
  }
}
