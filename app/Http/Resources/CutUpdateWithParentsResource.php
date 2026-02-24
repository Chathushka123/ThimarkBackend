<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CutUpdateWithParentsResource extends JsonResource
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
            'qty_json' => $this->qty_json,
            'qty_json_order' => $this->qty_json_order,
            'cut_plan' => $this->cut_plan,
            'fppo' => $this->fppo,
            'daily_shift_id'=> $this->daily_shift_id
        ];
    }
}
