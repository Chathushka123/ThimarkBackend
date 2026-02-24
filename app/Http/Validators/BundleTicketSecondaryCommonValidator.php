<?php

namespace App\Http\Validators;

class BundleTicketSecondaryCommonValidator
{
    public static function getCommonRules()
    {
        return [
            'bundle_id' => ['required', 'exists:bundles,id'],
            'original_quantity' => 'required|numeric',
            'scan_quantity' => ['nullable','numeric'],
            'packing_list_id' => ['required', 'exists:packing_list_id,id'],
            'bundle_ticket_id' => ['required' , 'exists:bundle_ticket_id'],
            'scan_date_time' => ['nullable'],
            'daily_scanning_slot_id' => ['nullable','sometimes',  'exists:daily_scanning_slots,id'],
            'daily_shift_team_id' => ['nullable', 'sometimes',  'exists:daily_shift_teams,id']
        ];
    }
}
