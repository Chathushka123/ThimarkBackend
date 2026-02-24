<?php

namespace App\Http\Validators;

use App\Http\Validators\FpoCutPlanCommonValidator;

class FpoCutPlanCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], FpoCutPlanCommonValidator::getCommonRules());
  }
}
