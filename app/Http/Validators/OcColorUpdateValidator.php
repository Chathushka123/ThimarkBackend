<?php

namespace App\Http\Validators;

use App\Http\Validators\OcColorCommonValidator;
use Illuminate\Validation\Rule;

class OcColorUpdateValidator
{
  public static function getUpdateRules($keyIgnore) {
    return array_merge([], OcColorCommonValidator::getCommonRules());
  }
}