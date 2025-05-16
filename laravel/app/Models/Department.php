<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $table = 'm_department';
    protected $fillable = ['id', 'name', 'created_at', 'updated_at', 'deleted_at', 'leader_id', 'is_delete'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'r_user_department', 'department_id', 'user_id');
    }
}
