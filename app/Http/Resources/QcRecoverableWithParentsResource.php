<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class QcRecoverableWithParentsResource extends JsonResource
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
            'bundle_ticket' => $this->bundle_ticket,
            'daily_scanning_slot' => $this->daily_scanning_slot,
            'recoverable_quantity' => $this->recoverable_quantity,
            'recovered_quantity' => $this->recovered_quantity
        ];
    }
}
