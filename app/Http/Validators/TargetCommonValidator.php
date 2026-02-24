<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class TargetCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'daily_scanning_slot_id' => ['required', 'exists:daily_scanning_slots,id'],
      'planned_target_qty' => ['required', 'numeric'],
      'revised_target_qty' => ['required', 'numeric']
    ];
  }
}
