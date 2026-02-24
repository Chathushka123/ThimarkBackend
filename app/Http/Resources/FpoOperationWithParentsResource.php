<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FpoOperationWithParentsResource extends JsonResource
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
            'fpo' => new FpoWithParentsResource($this->fpo),
            'routing_operation' => new RoutingOperationWithParentsResource($this->routing_operation),
            'print_bundle' => $this->print_bundle,
            'wip_point' => $this->wip_point
        ];
    }
}
