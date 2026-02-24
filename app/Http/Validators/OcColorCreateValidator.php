<?php

namespace App\Http\Validators;

use App\Http\Validators\OcColorCommonValidator;

class OcColorCreateValidator
{
  public static function getCreateRules() {
    return array_merge([], OcColorCommonValidator::getCommonRules());
  }
}