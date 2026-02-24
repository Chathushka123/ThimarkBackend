<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StyleFabricWithParentsResource extends JsonResource
{

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'fabric' => $this->fabric,
            'style' => new StyleWithParentsResource($this->style)        
        ];
    }
}
