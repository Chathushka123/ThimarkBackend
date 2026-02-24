<?php

namespace App\Http\Validators;

use App\Http\Validators\PermissionCommonValidator;
use Illuminate\Validation\Rule;

class PermissionUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], PermissionCommonValidator::getCommonRules());
  }
}
