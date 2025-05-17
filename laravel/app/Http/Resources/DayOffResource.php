<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DayOffResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'description'   => $this->description,
            'day_off'       => \DateTime::createFromFormat('Y-m-d', $this->day_off)->format('d/m/Y'),
            'status'        => $this->status,
            'updated_at'    => $this->updated_at->format('Y-m-d H:i:s'),
            'created_at'    => $this->created_at->format('d/m/Y'),
            'salary'        => $this->salary,
        ];
    }
}
