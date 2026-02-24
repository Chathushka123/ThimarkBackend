<?php

namespace App\Http\Validators;

use App\Http\Validators\FpoFabricCommonValidator;
use Illuminate\Validation\Rule;

class FpoFabricCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([], FpoFabricCommonValidator::getCommonRules());
  }
}
