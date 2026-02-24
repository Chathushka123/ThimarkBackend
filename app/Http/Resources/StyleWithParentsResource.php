<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StyleWithParentsResource extends JsonResource
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
            'routing' => $this->routing
        ];
    }
}
