<?php

namespace App\Http\Validators;

use App\Http\Validators\DowntimeDefinitionCommonValidator;
use Illuminate\Validation\Rule;

class DowntimeDefinitionCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], DowntimeDefinitionCommonValidator::getCommonRules());
  }
}
