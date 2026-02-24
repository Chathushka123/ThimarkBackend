<?php

namespace App\Http\Validators;

use App\Http\Validators\FpoFabricCommonValidator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class FpoFabricUpdateValidator
{
  public static function getUpdateRules()
  {
    return array_merge([], FpoFabricCommonValidator::getCommonRules());
  }
}
