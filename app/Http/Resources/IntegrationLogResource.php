<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationLogResource extends JsonResource
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
                        'process_name' => $this-> process_name,
                        'file_name' => $this-> file_name,
                        'status' => $this-> status,
                        'error_count' => $this-> error_count,
                        'start_time' => $this-> start_time,
                        'end_time' => $this-> end_time,
                    ];
    }
}
