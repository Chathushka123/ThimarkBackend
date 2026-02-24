<?php

namespace App\Http\Validators;

use App\Http\Validators\PermissionCommonValidator;

class PermissionCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], PermissionCommonValidator::getCommonRules());
  }
}
