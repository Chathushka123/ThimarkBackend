<?php

namespace App\Http\Validators;

use App\Http\Validators\PackingListCommonValidator;

class PackingListCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], PackingListCommonValidator::getCommonRules());
  }
}
