<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleProgramAssignment extends Model
{
    protected $fillable = [
        'user_id', 'role_name', 'program_id', 'batch_id',
        'is_active', 'assigned_by', 'assigned_at',
        'revoked_by', 'revoked_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
        'is_active'   => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function revokedBy()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public static function getAssignmentsForUser(User $user)
    {
        return static::where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['program', 'batch'])
            ->get();
    }

    public static function getUsersWithRole(string $role, ?int $programId = null)
    {
        $query = static::where('role_name', $role)->where('is_active', true);

        if ($programId !== null) {
            $query->where(function ($q) use ($programId) {
                $q->where('program_id', $programId)->orWhereNull('program_id');
            });
        }

        return $query->with('user')->get()->pluck('user');
    }
}
