<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FpoResource extends JsonResource
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
            'wfx_fpo_no' => $this->wfx_fpo_no,
            'qty_json' => $this->qty_json,
            'qty_json_order' => $this->qty_json_order,
            'soc_id' => $this->soc_id,
            'priority_seq' => $this->qty_json_order
        ];
    }
}
