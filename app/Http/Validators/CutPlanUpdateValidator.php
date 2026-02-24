<?php

namespace App\Http\Validators;

use App\Http\Validators\CutPlanCommonValidator;
use Illuminate\Validation\Rule;

class CutPlanUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([], CutPlanCommonValidator::getCommonRules());
  }
}
