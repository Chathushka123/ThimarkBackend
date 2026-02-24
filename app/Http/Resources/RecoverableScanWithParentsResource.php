<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RecoverableScanWithParentsResource extends JsonResource
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
            'bundle_ticket' => new BundleTicketWithParentsResource($this->bundle_ticket),
            'daily_scanning_slot' => new DailyScanningSlotWithParentsResource($this->daily_scanning_slot),
            'quantity' => $this->quantity
        ];
    }
}
