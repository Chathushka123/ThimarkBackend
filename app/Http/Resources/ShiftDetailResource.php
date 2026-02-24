<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShiftDetailResource extends JsonResource
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
            'shift_id' => $this->shift_id,
            'day' => $this->day,
            'shift_duration' => $this->shift_duration,
            'no_of_slots' => $this->no_of_slots,
            'slot_duration' => $this->slot_duration
        ];
    }
}
