<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FpoWithParentsResource extends JsonResource
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
            'priority_seq' => $this->qty_json_order,
            'soc' => new SocWithParentsResource($this->soc)
        ];
    }
}
