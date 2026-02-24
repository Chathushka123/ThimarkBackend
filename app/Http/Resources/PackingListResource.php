<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PackingListResource extends JsonResource
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
            'packing_list_date' => $this-> packing_list_date,
            'packing_list_delivery_date' => $this-> packing_list_delivery_date,
            'vpo' => $this-> vpo,
            'shipment_mode' => $this-> shipment_mode,
            'volume_weight' => $this-> volume_weight,
            'cbm' => $this-> cbm,
            'parameter_type' => $this->parameter_type,
            'carton_number_format_id' => $this->carton_number_format_id,
            'sorting_json'=> $this->sorting_json,
            'description'=>$this->description,
            'destination'=>$this->destination,
            'style_id'=>$this->style_id,
            'revision_no'=>$this->revision_no
              ];
    }
}
