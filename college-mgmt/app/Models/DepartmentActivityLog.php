<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentActivityLog extends Model
{
    protected $fillable = [
        'department_id', 'actor_user_id', 'target_user_id', 'action',
        'subject_type', 'subject_id', 'description', 'metadata', 'ip_address',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function department() { return $this->belongsTo(Department::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_user_id'); }
    public function target() { return $this->belongsTo(User::class, 'target_user_id'); }
    public function subject() { return $this->morphTo(); }
}
