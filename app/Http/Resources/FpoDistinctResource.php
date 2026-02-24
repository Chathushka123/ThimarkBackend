<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FpoDistinctResource extends JsonResource
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
            'wfx_fpo_no' => $this->wfx_fpo_no,
            'qty_json' => json_decode($this->qty_json),
            'wfx_soc_no' => $this->wfx_soc_no,
        ];
    }
}
