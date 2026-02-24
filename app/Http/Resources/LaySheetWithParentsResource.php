<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LaySheetWithParentsResource extends JsonResource
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
            'sheet_no' => $this->sheet_no,
            'combine_order' =>$this->combine_order,
            'fpo_fabric' => new FpoFabricWithParentsResource($this->fpo_fabric)
        ];
    }
}
