<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
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
            'report_date' => $this->report_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'daily_team_employee_id' => $this->daily_team_employee_id
        ];
    }
}
