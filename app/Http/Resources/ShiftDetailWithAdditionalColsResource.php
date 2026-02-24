<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShiftDetailWithAdditionalColsResource extends JsonResource
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
            'daily_shift_team_id' => null,
            'shift_detail_id' => $this->id,
            'scan_frequency' => null,
            'holiday' => null,
            'start_date' => null,
            'end_date' => null,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'hours' => $this->hours,
            'break_hours' => $this->break_hours,
            'overlap_two_days' => $this->overlap_two_days,
            'shift' => new ShiftWithParentsResource($this->shift),
            'day' => $this->day,
            'created_at' => $this->created_at,
            'updated_at' => $this->created_at
        ];
    }
}
