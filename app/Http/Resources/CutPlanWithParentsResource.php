<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CutPlanWithParentsResource extends JsonResource
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
            'cut_no' => $this->cut_no,
            'value_json' => $this->value_json,
            'qty_json_order' => $this->qty_json_order,
            'ratio_json' => $this->ratio_json,
            'max_plies' => $this->max_plies,
            'style_fabric' => new StyleFabricWithParentsResource($this->style_fabric),
            'combine_order_id' => $this->combine_order_id,
            "main_fabric" => $this->main_fabric,
            'yrds'=>$this->yrds,
            'inch'=>$this->inch,
            'acc_width'=>$this->acc_width
            // 'fpo_id' => $this->fpo_id,
            // 'fppo_id' => $this->fppo_id
        ];
    }
}
