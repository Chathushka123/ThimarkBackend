<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeWithParentsResource extends JsonResource
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
            'emp_code' => $this->emp_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'base_team' => $this->base_team,
            'employee_type' => $this->employee_type,
            'supervisor' => $this->supervisor,
            'employee_status' => $this->employee_status
        ];
    }
}
