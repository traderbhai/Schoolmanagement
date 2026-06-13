<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class AdmissionSensitiveAuditService
{
    public function record(string $action, ?Model $subject, ?User $actor, ?string $reason = null, array $before = [], array $after = [], array $metadata = []): int
    {
        return DB::table('admission_sensitive_audit_events')->insertGetId([
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'actor_user_id' => $actor?->id,
            'reason' => $reason,
            'route_name' => request()?->route()?->getName(),
            'before' => $before ? json_encode($before) : null,
            'after' => $after ? json_encode($after) : null,
            'metadata' => json_encode($metadata + ['v' => '0.039']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
