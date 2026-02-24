<?php

namespace App\Http\Validators;

use App\Http\Validators\DailyScanningSlotCommonValidator;
use Illuminate\Validation\Rule;

class DailyScanningSlotUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([
      'seq_no' => ['required', 'numeric']
    ], DailyScanningSlotCommonValidator::getCommonRules());
  }
}
