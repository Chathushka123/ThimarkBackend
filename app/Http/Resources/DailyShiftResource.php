<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DailyShiftResource extends JsonResource
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
            'shift_detail_id' => $this->shift_detail_id,
            'start_date_time' => $this->start_date_time,
            'end_date_time' => $this->end_date_time,
            'break' => $this->break,
            'frequency' => $this->scan_frequency,
            'holiday' =>  $this->holiday,
            'mid_night_cross' =>   $this->mid_night_cross
        ];
    }
}
