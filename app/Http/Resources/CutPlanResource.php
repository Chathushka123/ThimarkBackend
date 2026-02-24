<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CutPlanResource extends JsonResource
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
            'cut_no' => $this->sheet_no,
            'value_json' => $this->value_json,
            'ratio_json' => $this->ratio_json,
            'total_plies' => $this->total_plies,
            'combine_order_id' => $this->combine_order_id,
            'main_fabric' => $this->main_fabric,
            'qty_json_order' => $this->qty_json_order,
            'style_fabric_id' => $this->style_fabric_id,
            'fpo_id' => $this->fpo_id,
            'fppo_id' => $this->fppo_id,
            'yrds'=>$this->yrds,
            'inch'=>$this->inch,
            'acc_width'=>$this->acc_width
        ];
    }
}
