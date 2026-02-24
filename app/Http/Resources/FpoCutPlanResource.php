<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FpoCutPlanResource extends JsonResource
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
            'line_no' => $this->line_no,
            'qty_json' => $this->qty_json,
            'cut_plan_id' => $this->cut_plan_id,
            'fpo_id' => $this->fpo_id,
            'fppo_id' => $this->fppo_id
        ];
    }
}
