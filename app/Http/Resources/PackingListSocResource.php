<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PackingListSocResource extends JsonResource
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
            'soc_id' => $this-> soc_id,
            'packing_list_id' => $this-> packing_list_id,
            'quantity_json' => $this-> quantity_json,
            'quantity_json_order' => $this-> quantity_json_order,
            'pack_ratio' => $this-> pack_ratio,
            'parameter_type' => $this->parameter_type,
            'carton_number_format_id' => $this->carton_number_format_id
        ];
    }
}
