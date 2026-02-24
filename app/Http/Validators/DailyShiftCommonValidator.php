<?php

namespace App\Http\Validators;

class DailyShiftCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'current_date' => ['sometimes', 'date'],
      'shift_detail_id' => ['required', 'exists:shift_details,id'],
      'start_date_time' => ['required'],
      'end_date_time' => ['required'],
      'break' => ['nullable', 'sometimes', 'numeric'],
      //'frequency' => ['sometimes', 'numeric'],
      'over_time_hours' => ['nullable', 'sometimes', 'numeric'],
      'holiday' => ['nullable', 'sometimes', 'boolean'],
      'mid_night_cross' => ['nullable', 'sometimes', 'boolean']
    ];
  }
}
