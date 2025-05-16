<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DayOff extends Model
{
    use HasFactory;
    protected $table = 'm_day_offs';
    protected $fillable = [ 'id', 'title', 'day_off', 'status', 'description', 'started_at', 'ended_at',
        'created_at', 'updated_at', 'deleted_at', 'salary', 'country', 'is_delete'];

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
