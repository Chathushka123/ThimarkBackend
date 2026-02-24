<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FpoShrinkedResource extends JsonResource
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
            'wfx_fpo_no' => $this->wfx_fpo_no,
            'wfx_soc_no' => $this->soc->wfx_soc_no,
            'wfx_oc_no' => empty($this->soc->oc) ? null : $this->soc->oc->wfx_oc_no,
        ];
    }
}
