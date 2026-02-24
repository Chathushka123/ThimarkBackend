<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JobCardBundleWithParentsResource extends JsonResource
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
            'job_card' => new JobCardWithParentsResource($this->job_card),
            'bundle' => new BundleWithParentsResource($this->bundle),
            'original_quantity' => $this->original_quantity,
            'resized_quantity' => $this->resized_quantity
        ];
    }
}
