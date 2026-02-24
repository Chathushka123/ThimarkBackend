<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RecoverableScanResource extends JsonResource
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
            'current_date' => $this->current_date,
            'bundle_ticket_id' => $this->bundle_ticket_id,
            'daily_scanning_slot_id' => $this->daily_scanning_slot_id,
            'quantity' => $this->quantity
        ];
    }
}
