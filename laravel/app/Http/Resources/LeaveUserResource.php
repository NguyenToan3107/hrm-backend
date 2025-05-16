<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'idkey'                  => $this->idkey,
            'employee_name'          => $this->user->fullname,
            'description'            => $this->description,
            'salary'                 => $this->salary,
            'created_at'             => $this->created_at->format('d/m/Y'),
            'status'                 => $this->status,
            'day_leaves'             => \DateTime::createFromFormat('Y-m-d', $this->day_leave)->format('d/m/Y'),
            'approver_id'            => $this->approver_id,
            'approver_idkey'         => $this->userApprove->idkey ?? null,
            'approver_name'          => $this->userApprove->fullname ?? null,
            'approval_date'          => ($date = \DateTime::createFromFormat('Y-m-d', $this->approval_date)) ? $date->format('d/m/Y') : null,
            'shift'                  => $this->shift,
            'other_info'             => $this->other_info,
            'user_id'                => $this->user->id,
            'user_idkey'             => $this->user->idkey,
            'user_status_working'    => $this->user->status_working,
            'phone'                  => $this->user->phone,
            'image'                  => $this->user->image,
            'image_root'             => $this->user->image_root,
            'time_off_hours'         => $this->user->time_off_hours,
            'cancel_request'         => $this->cancel_request,
            'cancel_request_desc'    => $this->cancel_request_desc,
            'time_source'            => $this->time_source,
            'updated_at'             => $this->updated_at->format('Y-m-d H:i:s'),
            'last_year_time_off'     => $this->user->last_year_time_off,
            'allocated_hour'         => $this->getHourLeaveWaiting(),
        ];
    }
}
