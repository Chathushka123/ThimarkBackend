<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DailyShiftTeamWithAdditionalColsResource extends JsonResource
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
            'daily_shift_team_id' => $this->id,
            'shift_detail_id' => null,
            'scan_frequency' => $this->scan_frequency,
            'total_target' => $this->total_target,
            'planned_sah' => $this->planned_sah,
            'planned_efficient' => $this->planned_efficient,
            'holiday' => $this->holiday,
            'start_date' => explode(" ", $this->start_date_time)[0],
            'end_date' =>  explode(" ", $this->end_date_time)[0],
            'start_time' =>  explode(" ", $this->start_date_time)[1],
            'end_time' => explode(" ", $this->end_date_time)[1],
            'hours' => null,
            'break_hours' => $this->break,
            'overlap_two_days' => null,
            'shift' => null,
            'day' => null,
            'created_at' => $this->created_at,
            'updated_at' => $this->created_at
        ];
    }
}
