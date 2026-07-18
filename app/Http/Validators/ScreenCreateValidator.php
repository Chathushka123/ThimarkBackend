<?php

namespace App\Http\Validators;

use App\Http\Validators\ScreenCommonValidator;

class ScreenCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge(ScreenCommonValidator::getCommonRules(), [
      'screen_code' => ['required', 'unique:screens,screen_code'],
      'screen_name' => ['required', 'unique:screens,screen_name'],
    ]);
  }
}
