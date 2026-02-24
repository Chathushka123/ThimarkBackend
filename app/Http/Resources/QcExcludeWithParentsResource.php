<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class QcExcludeWithParentsResource extends JsonResource
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
            'daily_scanning_slot' => new DailyScanningSlotWithParentsResource($this->daily_scanning_slot),
            'bundle_ticket' => new BundleTicketWithParentsResource($this->bundle_ticket),
            'exclude_type' => $this->exclude_type,
            'quantity' => $this->quantity
        ];
    }
}
