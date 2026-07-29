<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DailyShiftTeamWithParentsResource extends JsonResource
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
            'daily_shift_id' => $this->daily_shift_id,
            'team_id' => $this->team_id,
            'no_of_operators' => $this->no_of_operators,
            'start_date_time' => $this->start_date_time,
            'end_date_time' => $this->end_date_time,
            'active' => $this->active,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
