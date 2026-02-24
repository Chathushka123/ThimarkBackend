<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PackingSocConsumptionResource extends JsonResource
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
                        'packing_list_detail_id' => $this-> packing_list_detail_id,
                        'packing_list_soc_id' => $this-> packing_list_soc_id,
                        'qty_json' => $this-> qty_json,
                    ];
    }
}
