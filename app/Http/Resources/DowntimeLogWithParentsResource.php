<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DowntimeLogWithParentsResource extends JsonResource
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
            'downtime_definition' => $this->downtime_definition,
            'daily_scanning_slot' => $this->daily_scanning_slot,
            'downtime_minutes' => $this->downtime_minutes,
            'reason' => $this->reason
        ];
    }
}
