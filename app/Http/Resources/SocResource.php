<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SocResource extends JsonResource
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
            'wfx_soc_no' => $this->wfx_soc_no,
            'qty_json' => $this->qty_json,
            'qty_json_order' => $this->qty_json_order,
            'garment_color' => $this->garment_color,
            'pack_color' => $this->pack_color,
            'customer_style_ref' => $this->customer_style_ref,
            'kit_pack_id' => $this->kit_pack_id,
            'max_sequence'=>$this->max_sequence,
            'tolerance'=>$this->tolerance,
            'tolerance_json'=>$this->tolerance_json

        ];
    }
}
