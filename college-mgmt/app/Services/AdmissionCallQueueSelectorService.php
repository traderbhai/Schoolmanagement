<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdmissionCallQueueSelectorService
{
    public function nextFor(User $user): ?Model
    {
        return $this->eligibleRecords($user)->sortByDesc('queue_score')->first()?->record;
    }

    public function eligibleRecords(User $user, int $limit = 30): Collection
    {
        $leads = Lead::with(['program', 'callLogs'])
            ->when(! $this->seesAll($user), fn ($q) => $q->where(function ($scope) use ($user) {
                $scope->where('assigned_to', $user->id)->orWhere('current_handler_user_id', $user->id);
            }))
            ->whereNotIn('status', ['converted', 'not_interested', 'lost', 'spam'])
            ->limit($limit)
            ->get();

        $applicants = Applicant::with(['program', 'batch', 'user', 'callLogs'])
            ->when(! $this->seesAll($user), fn ($q) => $q->where(function ($scope) use ($user) {
                $scope->where('assigned_to', $user->id)->orWhere('current_handler_user_id', $user->id);
            }))
            ->whereNotIn('status', ['rejected', 'withdrawn', 'enrolled'])
            ->limit($limit)
            ->get();

        return $leads->merge($applicants)
            ->reject(fn (Model $record) => $this->isSkipped($record, $user))
            ->map(fn (Model $record) => (object) [
                'record' => $record,
                'type' => $record instanceof Lead ? 'lead' : 'applicant',
                'queue_score' => $this->score($record),
                'recommended_action' => $this->recommendedAction($record),
            ])
            ->values();
    }

    public function skip(Model $subject, User $user, string $reason, ?string $until = null): void
    {
        DB::table('admission_call_queue_skips')->insert([
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'user_id' => $user->id,
            'reason' => $reason,
            'skipped_until' => $until ?: now()->addHour(),
            'metadata' => json_encode(['source' => 'calling_desk']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function canAccess(Model $subject, User $user): bool
    {
        if ($this->seesAll($user)) {
            return true;
        }

        $visibleIds = app(DepartmentHierarchyService::class)
            ->visibleUserIds($user, 'ADM')
            ->push($user->id)
            ->unique();

        return $visibleIds->contains($subject->assigned_to)
            || $visibleIds->contains($subject->current_handler_user_id);
    }

    private function score(Model $record): int
    {
        $priority = ['urgent' => 60, 'high' => 45, 'normal' => 25, 'low' => 10][$record->priority ?? 'normal'] ?? 20;
        $sla = $record->sla_due_at && $record->sla_due_at->isPast() ? 50 : 0;
        $activity = $record->last_activity_at ? max(0, min(25, now()->diffInDays($record->last_activity_at) * 5)) : 25;
        $followup = DB::table('admission_reminder_schedules')
            ->where('subject_type', get_class($record))
            ->where('subject_id', $record->id)
            ->where('status', 'scheduled')
            ->where('due_at', '<=', now())
            ->exists() ? 35 : 0;
        $parent = DB::table('admission_parent_journeys')
            ->where('subject_type', get_class($record))
            ->where('subject_id', $record->id)
            ->where('next_due_at', '<=', now())
            ->exists() ? 30 : 0;

        return $priority + $sla + $activity + $followup + $parent;
    }

    private function recommendedAction(Model $record): string
    {
        if (DB::table('admission_parent_journeys')->where('subject_type', get_class($record))->where('subject_id', $record->id)->where('next_due_at', '<=', now())->exists()) {
            return 'Call parent or guardian';
        }

        if ($record->sla_due_at && $record->sla_due_at->isPast()) {
            return 'Close overdue SLA callback';
        }

        return $record->next_action ?: 'Call and confirm next admission step';
    }

    private function isSkipped(Model $record, User $user): bool
    {
        return DB::table('admission_call_queue_skips')
            ->where('subject_type', get_class($record))
            ->where('subject_id', $record->id)
            ->where('user_id', $user->id)
            ->where('skipped_until', '>', now())
            ->exists();
    }

    public function seesAll(User $user): bool
    {
        return method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'admission_head', 'director']);
    }
}
