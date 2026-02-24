<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FpoCutPlanWithParentsResource extends JsonResource
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
            'qty_json' => $this->qty_json,
            'cut_plan' => new CutPlanWithParentsResource($this->cut_plan),
            'fpo' => new FpoWithParentsResource($this->fpo),
            'fppo' => new FppoWithParentsResource($this->fppo)
        ];
    }
}
