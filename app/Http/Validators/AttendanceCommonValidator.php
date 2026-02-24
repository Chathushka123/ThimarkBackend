<?php

namespace App\Http\Validators;

class AttendanceCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'report_date' => ['required', 'date'],
      'start_time' => ['required', 'date_format:H:i'],
      'end_time' => ['required', 'date_format:H:i'],
      'daily_emp_allocation_id' => ['required', 'exists:daily_team_employees,id']
    ];
  }
}
