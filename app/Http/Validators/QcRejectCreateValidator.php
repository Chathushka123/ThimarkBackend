<?php

namespace App\Http\Validators;

use App\Http\Validators\QcRejectCommonValidator;
use Illuminate\Validation\Rule;

class QcRejectCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], QcRejectCommonValidator::getCommonRules());
  }
}
