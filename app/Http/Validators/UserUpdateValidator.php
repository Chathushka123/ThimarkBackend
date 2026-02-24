<?php

namespace App\Http\Validators;

use App\Http\Validators\UserCommonValidator;
use Illuminate\Validation\Rule;

class UserUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([
      'email' => ['sometimes', 'required', Rule::unique('users')->ignore($keyIgnore)]
    ], UserCommonValidator::getCommonRules());
  }
}
