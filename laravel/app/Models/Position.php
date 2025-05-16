<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use HasFactory;

    protected $table    = 'm_positions';
    protected $fillable = ['id', 'name', 'description', 'created_at', 'updated_at', 'deleted_at', 'is_delete'];

    public function users()
    {
        return $this->hasMany(User::class, 'position_id', 'id');
    }
}
