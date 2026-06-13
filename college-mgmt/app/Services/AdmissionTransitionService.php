<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdmissionTransitionService
{
    private array $allowed = [
        'lead' => [
            'new' => ['contacted', 'lost', 'spam'],
            'contacted' => ['interested', 'not_interested', 'lost'],
            'interested' => ['converted', 'lost'],
        ],
        'applicant' => [
            'draft' => ['submitted', 'withdrawn'],
            'submitted' => ['under_review', 'shortlisted', 'rejected', 'withdrawn'],
            'under_review' => ['shortlisted', 'selected', 'rejected', 'hold'],
            'shortlisted' => ['selected', 'waitlisted', 'rejected', 'reschedule'],
            'selected' => ['enrolled', 'deferred', 'withdrawn'],
            'waitlisted' => ['selected', 'rejected', 'withdrawn'],
        ],
        'assessment' => [
            'invited' => ['confirmed', 'rescheduled', 'cancelled', 'no_show'],
            'confirmed' => ['checked_in', 'no_show', 'rescheduled'],
            'checked_in' => ['waiting', 'in_progress', 'completed'],
            'waiting' => ['in_progress', 'completed', 'rescheduled'],
            'in_progress' => ['completed', 'rescheduled'],
        ],
        'seat' => [
            'held' => ['accepted', 'released', 'expired', 'extended'],
            'accepted' => ['enrolled', 'released', 'deferred'],
        ],
    ];

    public function transition(Model $subject, string $toStatus, ?User $actor, string $reason, ?string $type = null): void
    {
        $type = $type ?: $this->typeFor($subject);
        $column = $type === 'assessment' ? 'lifecycle_status' : 'status';
        $from = (string) ($subject->{$column} ?? 'new');
        $allowed = $this->allowed[$type][$from] ?? [];
        $blocked = ! in_array($toStatus, $allowed, true) && $from !== $toStatus;

        $this->event($subject, $from, $toStatus, $actor, $reason, $blocked, $type, $blocked ? ["{$from} cannot move to {$toStatus}."] : []);

        if ($blocked) {
            throw ValidationException::withMessages(['status' => "{$from} cannot move to {$toStatus}."]);
        }

        $subject->forceFill([$column => $toStatus])->save();
    }

    public function event(Model $subject, ?string $from, string $to, ?User $actor, ?string $reason, bool $blocked, string $key, array $blockers = []): void
    {
        DB::table('admission_transition_events')->insert([
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'transition_key' => $key,
            'from_status' => $from,
            'to_status' => $to,
            'actor_user_id' => $actor?->id,
            'reason' => $reason,
            'blocked' => $blocked,
            'blockers' => $blockers ? json_encode($blockers) : null,
            'metadata' => json_encode(['v' => '0.039']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function typeFor(Model $subject): string
    {
        return str_contains(get_class($subject), 'Applicant') ? 'applicant' : 'lead';
    }
}
