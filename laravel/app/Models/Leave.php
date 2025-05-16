<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    use HasFactory;

    protected $table = 't_leaves';
    protected $fillable = [
        'id',
        'idkey',
        'user_id',
        'title',
        'status',
        'salary',
        'description',
        'started_at',
        'end_at',
        'day_leave',
        'created_at',
        'updated_at',
        'deleted_at',
        'country',
        'is_delete',
        'approver_id',
        'shift',
        'other_info',
        'approval_date',
        'cancel_request',
        'time_source',
        'cancel_request_desc'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function userApprove()
    {
        return $this->belongsTo(User::class, 'approver_id', 'id');
    }
    public function getHourLeaveWaiting()
    {
        return $this->user()
            ->join('t_leaves', 'm_users.id', '=', 't_leaves.user_id')
            ->selectRaw('SUM(CASE
            WHEN t_leaves.shift = 0 THEN 8
            WHEN t_leaves.shift = 1 THEN 4
            WHEN t_leaves.shift = 2 THEN 4
            END) as Allocated')
            ->where('t_leaves.status', STATUS_PENDING_APPROVAL)
            ->first()
            ->Allocated;
    }
}
