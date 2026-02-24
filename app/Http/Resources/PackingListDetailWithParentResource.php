<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PackingListDetailWithParentsResource extends JsonResource
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
                         'carton_number' => $this-> carton_number,
                          'qty_json' => $this-> qty_json,
                          'total' => $this-> total,
                          'manually_modified' => $this-> manually_modified,
                          'packing_list_id' => $this-> packing_list_id,
                          'carton_packing_list_id' => $this->carton_packing_list_id,
                          'carton_no2' => $this->carton_no2
                     ];
    }
}
