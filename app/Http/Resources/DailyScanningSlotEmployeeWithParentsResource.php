<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DailyScanningSlotEmployeeWithParentsResource extends JsonResource
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
            'employee' => new EmployeeWithParentsResource($this->employee_id),
            'daily_scanning_slot_id' => new DailyScanningSlotWithParentsResource($this->daily_scanning_slot_id),
            'daily_shift_team_id' => new DailyShiftTeamWithParentsResource($this->daily_shift_team_id),
        ];
    }
}
