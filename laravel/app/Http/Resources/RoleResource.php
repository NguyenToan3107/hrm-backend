<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'description'          => $this->description,
            'role_name'            => $this->role_name,
            'employee_number'      => $this->users_count,
            'updated_at'           => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
