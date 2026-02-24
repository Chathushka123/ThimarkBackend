<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DailyTeamSlotTargetResource extends JsonResource
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
            'daily_scanning_slot' => $this->daily_scanning_slot,
            'daily_shift_team' => $this->daily_shift_team,
            'target' => $this->target
        ];
    }
}
