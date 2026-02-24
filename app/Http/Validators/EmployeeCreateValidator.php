<?php

namespace App\Http\Validators;

use App\Http\Validators\EmployeeCommonValidator;
use Illuminate\Validation\Rule;

class EmployeeCreateValidator
{
  public static function getCreateRules()
  {
    return array_merge([
      'emp_code' => ['required', Rule::unique('employees')]
    ], EmployeeCommonValidator::getCommonRules());
  }
}
