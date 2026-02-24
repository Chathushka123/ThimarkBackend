<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShiftDetailWithParentsResource extends JsonResource
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
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'hours' => $this->hours,
            'break_hours' => $this->break_hours,
            'overlap_two_days' => $this->overlap_two_days,
            'shift' => new ShiftWithParentsResource($this->shift),
            'day' => $this->day
        ];
    }
}
