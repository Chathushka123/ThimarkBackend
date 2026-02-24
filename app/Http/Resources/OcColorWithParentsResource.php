<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OcColorWithParentsResource extends JsonResource
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
            'oc' => new OcWithParentsResource($this->oc),
            'garment_color' => $this->garment_color,
            'qty_json' => $this->qty_json
        ];
    }
}
