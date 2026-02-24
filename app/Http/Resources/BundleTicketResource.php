<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BundleTicketResource extends JsonResource
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
            'original_quantity' => $this->original_quantity,
            'scan_quantity' => $this->scan_quantity,
            'scan_hour_id' => $this->scan_hour_id,
            'fpo_operation_id' => $this->fpo_operation_id,
            'packing_list_id'=>$this->packing_list_id,
        ];
    }
}
