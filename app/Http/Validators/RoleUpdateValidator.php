<?php

namespace App\Http\Validators;

use App\Http\Validators\RoleCommonValidator;
use Illuminate\Validation\Rule;

class RoleUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([
      'role_code' => ['sometimes', 'required', Rule::unique('roles')->ignore($keyIgnore)]
    ], RoleCommonValidator::getCommonRules());
  }
}
