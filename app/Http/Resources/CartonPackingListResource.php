<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CartonPackingListResource extends JsonResource
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
            'packing_list_id' => $this->packing_list_id,
            'carton_id' => $this->carton_id,
            'ratio_json' => $this->ratio_json,
            'no_of_cartons' => $this->no_of_cartons,
            'total_quantity' => $this->total_quantity,
            'pcs_per_carton' => $this->pcs_per_carton,
            'weight_per_piece' => $this->weight_per_piece,
            'calculated_no_of_cartons' => $this->calculated_no_of_cartons,
            'customer_size_code' => $this->customer_size_code
        ];
    }
}
