<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LaySheetResource extends JsonResource
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
            'sheet_no' => $this->sheet_no,
            'combine_order_id' => $this->combine_order_id,
            'fpo_fabric_id' => $this->fpo_fabric_id
        ];
    }
}
