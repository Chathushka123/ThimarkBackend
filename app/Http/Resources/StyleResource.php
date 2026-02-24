<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StyleResource extends JsonResource
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
            'style_code' => $this->style_code,
            'description' => $this->description,
            'size_fit' => $this->size_fit,
            'size_fit_json' => $this->size_fit_json,
            'routing_id' => $this->routing_id
        ];
    }
}
