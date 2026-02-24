<?php

namespace App\Http\Validators;

use App\Http\Validators\FpoCutPlanCommonValidator;
use Illuminate\Validation\Rule;

class FpoCutPlanUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], FpoCutPlanCommonValidator::getCommonRules());
  }
}
