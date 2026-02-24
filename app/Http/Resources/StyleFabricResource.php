<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StyleFabricResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'fabric' => $this->fabric,
            'style_id' => $this->style_id           

        ];
    }
}
