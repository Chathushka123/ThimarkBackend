<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class QcRejectCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'daily_scanning_slot_id' => ['required', 'exists:daily_scanning_slots,id'],
      'bundle_ticket_id' => ['required', 'exists:bundle_tickets,id'],
      'quantity' => ['required', 'numeric'],
      'reject_reason' => ['required']
    ];
  }
}
