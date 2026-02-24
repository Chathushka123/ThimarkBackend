<?php

namespace App\Http\Validators;

use App\Http\Validators\UserCommonValidator;
use Illuminate\Validation\Rule;

class UserCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(['email' => 'required|unique:users',], UserCommonValidator::getCommonRules());
  }
}
