<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentImpersonationSession extends Model
{
    protected $fillable = [
        'department_id', 'actor_user_id', 'target_user_id', 'started_at',
        'ended_at', 'ip_address', 'reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function department() { return $this->belongsTo(Department::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_user_id'); }
    public function target() { return $this->belongsTo(User::class, 'target_user_id'); }
}
