<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JobCardWithParentsResource extends JsonResource
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
            'job_no' => $this->job_no,
            'fpo' => new FpoWithParentsResource($this->fpo),
            'team' => new TeamWithParentsResource($this->team),
            'trims_required' => $this->trims_required,
            'job_card_date' => $this->job_card_date,
            'status' => $this->status,
            'packing_list_no' => $this->packing_list_no,
            'daily_shift_id' => $this->daily_shift_id
        ];
    }
}
