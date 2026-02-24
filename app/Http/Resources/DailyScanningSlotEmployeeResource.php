<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DailyScanningSlotEmployeeResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'daily_scanning_slot_id' => $this->daily_scanning_slot_id,
            'daily_shift_team_id' => $this->daily_shift_team_id
        ];
    }
}
