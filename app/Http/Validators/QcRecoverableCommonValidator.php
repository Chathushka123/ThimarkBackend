<?php

namespace App\Http\Validators;

use Illuminate\Validation\Rule;

class QcRecoverableCommonValidator
{
  public static function getCommonRules()
  {
    return [
      'current_date' => ['required', 'date'],
      'bundle_ticket_id' => ['nullable', 'exists:bundle_tickets,id'],
      'daily_scanning_slot_id' => ['nullable', 'exists:daily_scanning_slots,id'],
      'recoverable_quantity' => ['required', 'numeric'],
      'recovered_quantity' => ['nullable', 'numeric']
    ];
  }
}
