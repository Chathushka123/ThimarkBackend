<?php

namespace App\Http\Validators;

class BundleTicketCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'bundle_id' => ['required', 'exists:bundles,id'],
      'original_quantity' => 'required|numeric',
      'scan_quantity' => ['nullable','numeric'],
      'fpo_operation_id' => ['required', 'exists:fpo_operations,id'],
      'scan_date_time' => ['nullable'],
      'daily_scanning_slot_id' => ['nullable','sometimes',  'exists:daily_scanning_slots,id'],
      'daily_shift_team_id' => ['nullable', 'sometimes',  'exists:daily_shift_teams,id'],
      'direction' => ['required']
    ];
  }
}


