<?php

namespace App\Http\Validators;

use App\Http\Validators\DowntimeDefinitionCommonValidator;
use Illuminate\Validation\Rule;

class DowntimeDefinitionUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], DowntimeDefinitionCommonValidator::getCommonRules());
  }
}
