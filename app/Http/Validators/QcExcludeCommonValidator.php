<?php

namespace App\Http\Validators;

class QcExcludeCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'daily_scanning_slot_id' => ['sometimes|required', 'exists:daily_scanning_slots,id'],
      'bundle_ticket_id' => ['sometimes|required', 'exists:bundle_tickets,id'],
      'exclude_type' => ['sometimes|required'],
      'quantity' => ['sometimes|required', 'numeric']
    ];
  }
}
