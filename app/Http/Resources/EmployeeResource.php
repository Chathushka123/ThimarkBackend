<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
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
            'base_team_id' => $this->base_team_id,
            'employee_type' => $this->employee_type,
            'supervisor_id' => $this->supervisor_id,
            'employee_status' => $this->employee_status
        ];
    }
}
