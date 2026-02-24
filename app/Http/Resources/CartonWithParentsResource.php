<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CartonWithParentsResource extends JsonResource
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
                'carton_type' => $this-> carton_type,
                'uom' => $this->uom,
                'height' => $this->height,
                'width' => $this->width,
                'length' => $this->length,
                'weight' => $this-> weight,
                ];
    }
}
