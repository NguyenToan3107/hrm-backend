<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Cviebrock\EloquentSluggable\Sluggable;
use DateTimeInterface;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;
    use Sluggable;
    use HasRoles;
    //    use SoftDeletes;

    protected $table = 'm_users';

    protected $fillable = [
        'id',
        'idkey',
        'leader_id',
        'fullname',
        'username',
        'slug',
        'is_slug_override',
        'email',
        'email_verified_at',
        'password',
        'address',
        'phone',
        'role',
        'birth_day',
        'description',
        'content',
        'image',
        'image_extension',
        'time_off_hours',
        'status',
        'started_at',
        'ended_at',
        'published_at',
        'created_at',
        'updated_at',
        'deleted_at',
        'status_working',
        'country',
        'position_id',
        'last_year_time_off',
        'password_changed',
        'gender',
        'image_root'
    ];

    protected $hidden = [
        'password',
    ];
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    //    // Sử dụng convert dịnh dạng date time chuẩn cho API
    //    protected function serializeDate(DateTimeInterface $date)
    //    {
    //        return $date->format('Y-m-d H:i:s');
    //    }


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'fullname'
            ]
        ];
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'r_user_department', 'user_id', 'department_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    public function dayOffs()
    {
        return $this->hasMany(DayOff::class);
    }

    public function leaderId()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function getHourLeaveWaiting()
    {
        return $this->leaves()
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
