<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BundleBinWithParentsResource extends JsonResource
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
            'qc_reject' => $this->qc_reject,
            'job_card_bundle' => $this->job_card_bundle,
            'created_by' => $this->created_by,
            'bundle' => $this->bundle
        ];
    }
}
