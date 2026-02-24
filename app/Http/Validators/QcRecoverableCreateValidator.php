<?php

namespace App\Http\Validators;

use App\Http\Validators\QcRecoverableCommonValidator;
use Illuminate\Validation\Rule;

class QcRecoverableCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], QcRecoverableCommonValidator::getCommonRules());
  }
}
