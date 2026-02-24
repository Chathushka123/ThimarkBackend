<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JobCardBundleResource extends JsonResource
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
            'line_no' => $this->line_no,
            'job_card_id' => $this->job_card_id,
            'bundle_id' => $this->bundle_id,
            'original_quantity' => $this->original_quantity,
            'resized_quantity' => $this->resized_quantity
        ];
    }
}
