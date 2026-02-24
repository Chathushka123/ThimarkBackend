<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FpoFabricWithParentsResource extends JsonResource
{

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'avg_consumption' => $this->avg_consumption,
            'style_fabric' => new StyleFabricWithParentsResource($this->style_fabric),
            'fpo' => new FpoWithParentsResource($this->fpo)
        ];
    }
}
