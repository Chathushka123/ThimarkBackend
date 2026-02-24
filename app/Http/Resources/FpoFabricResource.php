<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FpoFabricResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'fpo_id' => $this->fpo_id,
            'style_fabric_id' => $this->style_fabric_id     

        ];
    }
}
