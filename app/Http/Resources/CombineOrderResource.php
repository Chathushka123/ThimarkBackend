<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CombineOrdertResource extends JsonResource
{

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'combine_order_no' => $this->combine_order_no
        ];
    }
}
