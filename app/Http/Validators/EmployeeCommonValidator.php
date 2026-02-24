<?php

namespace App\Http\Validators;

use App\Employee;
use Illuminate\Validation\Rule;

class EmployeeCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'first_name' => ['required'],
      'last_name' => ['required'],
      'base_team_id' => ['nullable', 'sometimes', 'exists:teams,id'],
      'employee_type' => ['required', Rule::in(array_keys(Employee::EMPLOYEE_TYPES))],
      'supervisor_id' => ['nullable', 'sometimes', 'exists:employees,id'],
      'employee_status' => ['sometimes']
    ];
  }
}
