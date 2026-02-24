<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OcResource extends JsonResource
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
            'buyer_id' => $this->buyer_id,
            'style_id' => $this->style_id,
            'wfx_oc_no' => $this->wfx_oc_no,
            'pack_color' => $this->pack_color,
            'qty_json' => $this->qty_json,
            // 'buyer' => (new BuyerResource($this->buyer)),
            // 'buyer' => $this->buyer,
            // 'socs' => SocResource::collection($this->socs)
        ];
    }
}
