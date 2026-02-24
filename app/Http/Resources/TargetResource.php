<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TargetResource extends JsonResource
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
            'planned_target_qty' => $this->planned_target_qty,
            'revised_target_qty' => $this->revised_target_qty,
            'daily_scanning_slot_id' => $this->daily_scanning_slot_id
        ];
    }
}
