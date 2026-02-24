<?php

namespace App\Http\Validators;

use App\Http\Validators\CutPlanCommonValidator;

class CutPlanCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], CutPlanCommonValidator::getCommonRules());
  }
}
