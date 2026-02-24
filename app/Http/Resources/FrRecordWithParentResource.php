<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FrRecordWithParentsResource extends JsonResource
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
                         'run_date' => $this-> run_date,
                          'team_id' => $this-> team_id,
                          'total_planned_target' => $this-> total_planned_target,
                          'planned_efficiency' => $this-> planned_efficiency,
                          'planned_sah' => $this-> planned_sah,
                          'smv' => $this-> smv,
                          'style_code' => $this-> style_code,
                          'soc_no' => $this-> soc_no,
                          'fpo_no' => $this-> fpo_no,
                     ];
    }
}
