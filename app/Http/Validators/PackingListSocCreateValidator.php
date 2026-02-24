<?php

namespace App\Http\Validators;

use App\Http\Validators\ShiftDetailCommonValidator;

class PackingListSocCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], PackingListSocCommonValidator::getCommonRules());
  }
}
