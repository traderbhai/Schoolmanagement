<?php

namespace App\Services;

use App\Models\AdmissionObjectionEvent;
use App\Models\AdmissionScriptTemplate;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdmissionCallingDeskService
{
    public function dashboard(User $user): array
    {
        $selector = app(AdmissionCallQueueSelectorService::class);
        $queue = $selector->eligibleRecords($user, 40);
        $active = $queue->first()?->record;

        return [
            'active' => $active,
            'queue' => $queue,
            'script' => AdmissionScriptTemplate::where('is_active', true)->latest()->first(),
            'attempts_today' => $this->attemptsToday($user, $selector),
            'contact_rate' => $this->rate('connected', $user, $selector),
            'callback_due' => app(AdmissionReminderService::class)->queryFor($user, ['reason' => 'callback_retry'])
                ->where('due_at', '<=', now())
                ->count(),
            'objections' => $this->scopedObjections($user, $selector),
            'parent_due' => $this->scopedParentJourneys($user, $selector)->where('next_due_at', '<=', now())->count(),
        ];
    }

    private function attemptsToday(User $user, AdmissionCallQueueSelectorService $selector): int
    {
        return $this->callAttempts($user, $selector)->whereDate('attempted_at', today())->count();
    }

    private function rate(string $disposition, User $user, AdmissionCallQueueSelectorService $selector): int
    {
        $total = $this->callAttempts($user, $selector)->whereDate('attempted_at', today())->count();
        if ($total === 0) {
            return 0;
        }

        $matching = $this->callAttempts($user, $selector)->whereDate('attempted_at', today())->where('disposition', $disposition)->count();
        return (int) round(($matching / $total) * 100);
    }

    private function callAttempts(User $user, AdmissionCallQueueSelectorService $selector)
    {
        $query = DB::table('admission_call_attempts');

        if (! $selector->seesAll($user)) {
            $query->where('caller_user_id', $user->id);
        }

        return $query;
    }

    private function scopedObjections(User $user, AdmissionCallQueueSelectorService $selector)
    {
        $query = AdmissionObjectionEvent::with(['subject', 'type'])->latest();

        if (! $selector->seesAll($user)) {
            [$leadIds, $applicantIds] = $this->visibleSubjectIds($user);
            $query->where(function ($scope) use ($leadIds, $applicantIds) {
                $scope->where(function ($leadScope) use ($leadIds) {
                    $leadScope->where('subject_type', Lead::class)->whereIn('subject_id', $leadIds);
                })->orWhere(function ($applicantScope) use ($applicantIds) {
                    $applicantScope->where('subject_type', Applicant::class)->whereIn('subject_id', $applicantIds);
                });
            });
        }

        return $query->limit(8)->get();
    }

    private function scopedParentJourneys(User $user, AdmissionCallQueueSelectorService $selector)
    {
        $query = DB::table('admission_parent_journeys');

        if (! $selector->seesAll($user)) {
            [$leadIds, $applicantIds] = $this->visibleSubjectIds($user);
            $query->where(function ($scope) use ($leadIds, $applicantIds) {
                $scope->where(function ($leadScope) use ($leadIds) {
                    $leadScope->where('subject_type', Lead::class)->whereIn('subject_id', $leadIds);
                })->orWhere(function ($applicantScope) use ($applicantIds) {
                    $applicantScope->where('subject_type', Applicant::class)->whereIn('subject_id', $applicantIds);
                });
            });
        }

        return $query;
    }

    private function visibleSubjectIds(User $user): array
    {
        $visibleUserIds = app(AdmissionAccessPolicyService::class)
            ->visibleUserIds($user)
            ->push($user->id)
            ->unique();

        return [
            Lead::query()
                ->whereIn('assigned_to', $visibleUserIds)
                ->orWhereIn('current_handler_user_id', $visibleUserIds)
                ->pluck('id'),
            Applicant::query()
                ->whereIn('assigned_to', $visibleUserIds)
                ->orWhereIn('current_handler_user_id', $visibleUserIds)
                ->pluck('id'),
        ];
    }
}
