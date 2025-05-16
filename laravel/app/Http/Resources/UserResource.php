<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'idkey'                => $this->idkey,
            'fullname'             => $this->fullname ? $this->fullname : '',
            'phone'                => $this->phone,
            'birth_day'            => $this->birth_day ? \Carbon\Carbon::parse($this->birth_day)->format('d/m/Y') : null,
            'address'              => $this->address,
            'country'              => $this->country,
            'username'             => $this->username,
            'status'               => $this->status,
            'started_at'           => $this->started_at ? \Carbon\Carbon::parse($this->started_at)->format('d/m/Y') : null,
            'ended_at'             => $this->ended_at ? \Carbon\Carbon::parse($this->ended_at)->format('d/m/Y') : null,
            'department'           => $this->departments->pluck('id'),
            'position_id'          => $this->position_id,
            'position_name'        => $this->position ? $this->position->name : null,
            'time_off_hours'       => $this->time_off_hours,
            'status_working'       => $this->status_working,
            'email'                => $this->email,
            'updated_at'           => $this->updated_at->format('Y-m-d H:i:s'),
            'role'                 => [
                'name'        => $this->getRoleNames()->implode(', '),
                'permissions' => $this->getAllPermissions()->pluck('name')
            ],
            'image'                => $this->image,
            'leader_id'            => $this->leader_id,
            'leader_idKey'         => $this->leaderId ? $this->leaderId->idkey : null,
            'leader_name'          => $this->leaderId ? $this->leaderId->fullname : null,
            'password_changed'     => $this->password_changed,
            'gender'               => $this->gender,
            'last_year_time_off'   => $this->last_year_time_off,
            'hide_notification_to' => $this->hide_notification_to,
            'image_root'           => $this->image_root,
            'allocated_hour'       => $this->getHourLeaveWaiting(),
        ];
    }
}
