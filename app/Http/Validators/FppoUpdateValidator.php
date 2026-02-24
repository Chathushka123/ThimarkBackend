<?php

namespace App\Http\Validators;

use App\Http\Validators\FppoCommonValidator;
use Illuminate\Validation\Rule;

class FppoUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    
    return array_merge([], FppoCommonValidator::getCommonRules());
  }
}
