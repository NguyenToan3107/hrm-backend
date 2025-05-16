<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'permission_id'        => $this->id,
            'feature_name'         => $this->feature,
            'permission_cd'        => $this->permission_cd,
            'permission_nm'        => $this->permission_name,
            'permission_desc'      => $this->description,
            'level'                => $this->level
        ];
    }
}
