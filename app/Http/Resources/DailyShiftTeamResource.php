<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DailyShiftTeamResource extends JsonResource
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
            'team_id' => $this->team_id,
            'daily_shift_id' => $this->daily_shift_id,
            'start_date_time' => $this->start_date_time,
            'end_date_time' => $this->end_date_time,
            'break' => $this->break,
            'scan_frequency' => $this->scan_frequency,
            'total_target' => $this->total_target,
            'planned_sah' => $this->planned_sah,
            'planned_efficient' => $this->planned_efficient
        ];
    }
}
