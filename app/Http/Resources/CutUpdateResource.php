<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CutUpdateResource extends JsonResource
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
            'qty_json' => json_decode($this->qty_json),
            'qty_json_order' => $this->qty_json_order,
            'fppo_id' => $this->fppo_id,
            'cut_plan_id' => $this->cut_plan_id,
            'daily_shift_id'=> $this->daily_shift_id
        ];
    }
}
