<?php

namespace App\Http\Validators;

class ShiftDetailCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'start_time' => ['required'],
      'end_time' => ['required'],
      'hours' => ['required'],
      'break_hours' => ['required'],
      'overlap_two_days' => ['required'],
      'shift_id' => ['required']
    ];
  }
}
