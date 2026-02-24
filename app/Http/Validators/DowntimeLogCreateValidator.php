<?php

namespace App\Http\Validators;

use App\Http\Validators\DowntimeLogCommonValidator;
use Illuminate\Validation\Rule;

class DowntimeLogCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], DowntimeLogCommonValidator::getCommonRules());
  }
}
