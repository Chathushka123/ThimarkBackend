<?php

namespace App\Http\Validators;

use App\Http\Validators\ShiftDetailCommonValidator;

class PackingListDetailCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], PackingListDetailCommonValidator::getCommonRules());
  }
}
