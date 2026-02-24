<?php

namespace App\Http\Validators;

use App\Http\Validators\CartonNumberFormatCommonValidator;
use Illuminate\Validation\Rule;

class CartonNumberFormatUpdateValidator
{
  public static function getUpdateRules($keyIgnore, $rec)
  {
    return array_merge([
      'carton_type' => ['required', Rule::unique('cartons')->where(function ($query) use ($rec) {
        return $query->where('carton_type', $rec['carton_type']);      
      })->ignore($keyIgnore)]
      
    ], CartonNumberFormatCommonValidator::getCommonRules());
  }
}
