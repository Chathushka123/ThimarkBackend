<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BundleTicketSecondaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'bundle_id' => $this->bundle_id,
            'original_quantity' => $this->original_quantity,
            'scan_quantity' => $this->scan_quantity,
            'packing_list_id' => $this->packing_list_id,
            'bundle_ticket_id' => $this->bundle_ticket_id,
            'scan_date_time' => $this->scan_date_time,
            'daily_scanning_slot_id'=> $this->daily_scanning_slot_id,
            'daily_shift_team_id' => $this->daily_shift_team_id
        ];
    }
}
