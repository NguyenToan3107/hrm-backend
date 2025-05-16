<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'idkey'       => $this->idkey,
            'title'       => $this->title,
            'description' => $this->description,
            'day_leave'   => \DateTime::createFromFormat('Y-m-d', $this->day_leave)->format('d/m/Y'),
            'status'      => $this->status,
            'salary'      => $this->salary,
            'started_at'  => $this->started_at,
            'ended_at'    => $this->ended_at,
            'updated_at'  => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
