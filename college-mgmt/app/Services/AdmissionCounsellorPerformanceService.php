<?php

namespace App\Services;

use App\Models\AdmissionCallLog;
use App\Models\AdmissionCounsellorCoachingNote;
use App\Models\AdmissionCounsellorTarget;
use App\Models\AdmissionReminderSchedule;
use App\Models\Applicant;
use App\Models\User;
use Illuminate\Support\Carbon;

class AdmissionCounsellorPerformanceService
{
    public function dashboard(User $actor): array
    {
        $today = now()->toDateString();
        $targets = AdmissionCounsellorTarget::with('user')
            ->where('period_start', '<=', $today)
            ->where('period_end', '>=', $today)
            ->orderBy('user_id')
            ->get();

        $rows = $targets->map(fn ($target) => $this->scorecard($target));

        return [
            'rows' => $rows,
            'coaching' => AdmissionCounsellorCoachingNote::with(['counsellor', 'reviewer'])
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'stats' => [
                'counsellors_tracked' => $rows->count(),
                'calls_completed' => $rows->sum('actual_calls'),
                'followups_done' => $rows->sum('actual_followups'),
                'applications_created' => $rows->sum('actual_applications'),
                'open_coaching' => AdmissionCounsellorCoachingNote::where('status', 'open')->count(),
            ],
        ];
    }

    public function scorecard(AdmissionCounsellorTarget $target): array
    {
        $start = Carbon::parse($target->period_start)->startOfDay();
        $end = Carbon::parse($target->period_end)->endOfDay();
        $userId = $target->user_id;

        $actualCalls = AdmissionCallLog::where('caller_user_id', $userId)->whereBetween('created_at', [$start, $end])->count();
        $actualFollowups = AdmissionReminderSchedule::where(fn ($q) => $q
            ->where('owner_user_id', $userId)
            ->orWhere('assigned_to', $userId)
        )->whereBetween('created_at', [$start, $end])->count();
        $actualApplications = Applicant::where('assigned_to', $userId)->whereBetween('created_at', [$start, $end])->count();
        $actualEnrollments = Applicant::where('assigned_to', $userId)->where('status', 'enrolled')->whereBetween('updated_at', [$start, $end])->count();

        $callRate = $this->rate($actualCalls, $target->target_calls);
        $followupRate = $this->rate($actualFollowups, $target->target_followups);
        $applicationRate = $this->rate($actualApplications, $target->target_applications);
        $enrollmentRate = $this->rate($actualEnrollments, $target->target_enrollments);
        $overall = round(collect([$callRate, $followupRate, $applicationRate, $enrollmentRate])->filter(fn ($v) => $v !== null)->avg() ?? 0, 1);
        $scriptCompliance = app(AdmissionScriptComplianceService::class)->averageFor($target->user);

        return [
            'target' => $target,
            'user' => $target->user,
            'actual_calls' => $actualCalls,
            'actual_followups' => $actualFollowups,
            'actual_applications' => $actualApplications,
            'actual_enrollments' => $actualEnrollments,
            'call_rate' => $callRate,
            'followup_rate' => $followupRate,
            'application_rate' => $applicationRate,
            'enrollment_rate' => $enrollmentRate,
            'overall_rate' => $overall,
            'script_compliance' => $scriptCompliance,
            'band' => $overall >= 90 ? 'excellent' : ($overall >= 70 ? 'on_track' : 'needs_coaching'),
        ];
    }

    public function addCoachingNote(User $counsellor, User $reviewer, array $data): AdmissionCounsellorCoachingNote
    {
        return AdmissionCounsellorCoachingNote::create([
            'counsellor_user_id' => $counsellor->id,
            'reviewer_user_id' => $reviewer->id,
            'review_type' => $data['review_type'] ?? 'daily_review',
            'score_band' => $data['score_band'] ?? 'on_track',
            'strengths' => $data['strengths'] ?? null,
            'improvement_areas' => $data['improvement_areas'] ?? null,
            'action_plan' => $data['action_plan'] ?? null,
            'reviewed_for_date' => $data['reviewed_for_date'] ?? now()->toDateString(),
            'next_review_at' => $data['next_review_at'] ?? now()->addWeek()->toDateString(),
            'status' => $data['status'] ?? 'open',
            'metadata' => ['v' => '0.037'],
        ]);
    }

    private function rate(int $actual, int $target): ?float
    {
        return $target > 0 ? min(150, round(($actual / $target) * 100, 1)) : null;
    }
}
