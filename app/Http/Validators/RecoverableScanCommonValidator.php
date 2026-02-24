<?php

namespace App\Http\Validators;

class RecoverableScanCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'daily_scanning_slot_id' => ['sometimes|required', 'exists:daily_scanning_slots,id'],
      'bundle_ticket_id' => ['sometimes|required', 'exists:bundle_tickets,id'],
      'current_date' => ['sometimes|required'],
      'quantity' => ['sometimes|required', 'numeric']
    ];
  }
}
