<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BundleCutWithParentsResource extends JsonResource
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
            'bundle' => new BundleResource($this->bundle),
            'cut_update' => new CutUpdateResource($this->cut_update),
            'quantity' => $this->quantity
        ];
    }
}
