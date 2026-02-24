<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BundleTicketWithParentsResource extends JsonResource
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
            'original_quantity' => $this->original_quantity,
            'scan_quantity' => $this->scan_quantity,
            'scan_hour_id' => $this->scan_hour_id,
            'packing_list_id'=>$this->packing_list_id,
            'fpo_operation' => new FpoOperationResource($this->fpo_operation),
        ];
    }
}
