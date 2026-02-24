<?php

namespace App\Http\Validators;

class RoleCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'description' => ['required'],

    ];
  }
}
