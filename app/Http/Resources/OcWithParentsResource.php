<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OcWithParentsResource extends JsonResource
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
            'wfx_oc_no' => $this->wfx_oc_no,
            'pack_color' => $this->pack_color,
            'qty_json' => $this->qty_json,
            'buyer' => new BuyerWithParentsResource($this->buyer),
            'style' => new StyleWithParentsResource($this->style),
        ];
    }
}
