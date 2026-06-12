<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function log(string $action, Model $target, ?array $changes = null, ?int $actorId = null): self
    {
        return self::create([
            'actor_id'    => $actorId ?? auth()->id(),
            'action'      => $action,
            'target_type' => get_class($target),
            'target_id'   => $target->getKey(),
            'changes'     => $changes,
        ]);
    }

    public static function logRoleAssignment(User $actor, User $targetUser, Role $role, ?Program $program = null, ?array $changes = null): self
    {
        return self::create([
            'actor_id' => $actor->id,
            'action' => 'role_assigned',
            'target_type' => User::class,
            'target_id' => $targetUser->id,
            'changes' => [
                'role' => $role->name,
                'program' => $program?->name ?? 'All Programs',
                'user_name' => $targetUser->name,
                'user_email' => $targetUser->email,
                ...$changes ?? [],
            ],
        ]);
    }

    public static function logRoleRevoked(User $actor, User $targetUser, Role $role): self
    {
        return self::create([
            'actor_id' => $actor->id,
            'action' => 'role_revoked',
            'target_type' => User::class,
            'target_id' => $targetUser->id,
            'changes' => [
                'role' => $role->name,
                'user_name' => $targetUser->name,
                'user_email' => $targetUser->email,
            ],
        ]);
    }

    public static function logPermissionChanged(User $actor, Role $role, ?array $changes = null): self
    {
        return self::create([
            'actor_id' => $actor->id,
            'action' => 'permission_changed',
            'target_type' => Role::class,
            'target_id' => $role->id,
            'changes' => [
                'role' => $role->name,
                ...$changes ?? [],
            ],
        ]);
    }
}
