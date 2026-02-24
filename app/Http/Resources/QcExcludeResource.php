<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class QcExcludeResource extends JsonResource
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
            'daily_scanning_slot_id' => $this->daily_scanning_slot_id,
            'bundle_ticket_id' => $this->bundle_ticket_id,
            'exclude_type' => $this->exclude_type,
            'quantity' => $this->quantity
        ];
    }
}
