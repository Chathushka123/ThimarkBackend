<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class QcRejectResource extends JsonResource
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
            'daily_scanning_slot_id' => $this->daily_scanning_slot_id,
            'bundle_ticket_id' => $this->bundle_ticket_id,
            'quantity' => $this->quantity,
            'reject_reason' => $this->reject_reason,
        ];
    }
}
