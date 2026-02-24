<?php

namespace App\Http\Validators;

class DailyScanningSlotCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'from_date_time' => ['required'],
      'to_date_time' => ['required'],
      'duration_hours' => ['required', 'numeric'],
      'daily_shift_id' => ['required', 'exists:daily_shifts,id'],
      'planned_target' => ['nullable', 'sometimes', 'numeric'],
      'forecast' => ['nullable', 'sometimes', 'numeric'],
      'revised_target' => ['nullable', 'sometimes', 'numeric'],
      'actual' => ['nullable', 'sometimes', 'numeric']

    ];
  }
}
