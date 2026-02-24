<?php

namespace App\Http\Validators;

use App\Http\Validators\RoleCommonValidator;

class RoleCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'role_code' => ['sometimes', 'required', 'unique:roles,role_code']
    ], RoleCommonValidator::getCommonRules());
  }
}
