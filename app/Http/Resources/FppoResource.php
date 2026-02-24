<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FppoResource extends JsonResource
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
            'fppo_no' => $this->buyer_id,
            'qty_json' => $this->qty_json,
            'qty_json_order' => $this->qty_json_order,
            'fpo_id' => $this->fpo_id,
            'utilized' => $this->utilized,
            'wfx_fppo_no'=>$this->wfx_fppo_no
        ];
    }
}
