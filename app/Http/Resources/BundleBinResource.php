<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BundleBinResource extends JsonResource
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
            'created_date' => $this->created_date,
            'record_type' => $this->record_type,
            'size' => $this->size,
            'quantity' => $this->quantity,
            'utilized' => $this->utilized,
            'qc_reject_id' => $this->qc_reject_id,
            'job_card_bundle_id' => $this->job_card_bundle_id,
            'created_by_id' => $this->created_by_id,
            'bundle_id' => $this->bundle_id
        ];
    }
}
