<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class UserRole extends Model
{
    protected $fillable = [
        'user_id',
        'role_id',
        'program_id',
        'assigned_by',
        'active_until',
    ];

    protected $casts = [
        'active_until' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isActive(): bool
    {
        if ($this->active_until === null) {
            return true;
        }

        return now()->lessThanOrEqualTo($this->active_until);
    }
}
