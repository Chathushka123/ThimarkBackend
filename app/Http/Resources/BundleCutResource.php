<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BundleCutResource extends JsonResource
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
            'bundle_id' => $this->bundle_id,
            'cut_update_id' => $this->cut_update_id,
            'quantity' => $this->quantity
        ];
    }
}
