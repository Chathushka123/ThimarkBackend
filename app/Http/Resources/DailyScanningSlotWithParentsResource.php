<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DailyScanningSlotWithParentsResource extends JsonResource
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
            'from_date_time' => $this->from_date_time,
            'to_date_time' => $this->to_date_time,
            'duration_hours' => $this->duration_hours,
            'daily_shift' => new DailyShiftWithParentsResource($this->daily_shift_id),
            'seq_no' => $this->seq_no,
            'planned_target' => $this->planned_target, 
            'forecast' => $this->forecast,
            'revised_target' => $this->revised_target,
            'actual' => $this->actual
        ];
    }
}
