<?php

namespace App\Http\Validators;

use App\Http\Validators\ShiftCommonValidator;
use Illuminate\Support\Facades\Log;

class ShiftCreateValidator
{
  public static function getCreateRules()
  {
    Log::info('++++++++++++++++++');
    return array_merge([
      'shift_code' => 'required|unique:shifts,shift_code'
    ], ShiftCommonValidator::getCommonRules());
  }
}
