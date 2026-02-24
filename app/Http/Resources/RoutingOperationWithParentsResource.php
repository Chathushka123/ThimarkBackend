<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RoutingOperationWithParentsResource extends JsonResource
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
            'source' => $this->source,
            'wfx_seq' => $this->wfx_seq,
            'shop_floor_seq' => $this->shop_floor_seq,
            'level' => $this->level,
            'operation_code' => $this->operation_code,
            'description' => $this->description,
            'in' => $this->in,
            'out' => $this->out,
            'smv' => $this->smv,
            'smv_hist_1' => $this->smv_hist_1,
            'smv_hist_2' => $this->smv_hist_2,
            'print_bundle' => $this->print_bundle,
            'wip_point' => $this->wip_point,
            'parent_operation' => $this->parent_operation,
            'parallel_operation' =>$this->parallel_operation,
            'sort_seq' => $this->sort_seq,
            'routing' =>$this->routing
        ];
    }
}
