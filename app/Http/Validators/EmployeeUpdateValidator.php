<?php

namespace App\Http\Validators;

use App\Http\Validators\EmployeeCommonValidator;
use Illuminate\Validation\Rule;

class EmployeeUpdateValidator
{
  public static function getUpdateRules($keyIgnore)
  {
    return array_merge([
      'emp_code' => ['required', Rule::unique('employees')->ignore($keyIgnore)]
    ], EmployeeCommonValidator::getCommonRules());
  }
}
