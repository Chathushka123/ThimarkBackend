<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserWithParentsResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'role_id' => $this->role_id,
            'role' => $this->whenLoaded('role'),
            'is_active' => $this->is_active,
            'common_user' => $this->common_user,
            'common_user_state' => $this->common_user_state,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
