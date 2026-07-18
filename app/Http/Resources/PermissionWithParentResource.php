<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PermissionWithParentsResource extends JsonResource
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
            'screen_id' => $this->screen_id,
            'role_id' => $this->role_id,
            'grant' => $this->grant,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
