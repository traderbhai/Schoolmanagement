<?php

namespace App\Services;

use App\Models\AdmissionAssessmentPanelAssignment;
use App\Models\AdmissionReminderSchedule;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;

class AdmissionCounsellorDeskService
{
    public function __construct(
        private DepartmentHierarchyService $hierarchy,
        private AdmissionConversationTimelineService $timeline,
        private AdmissionCounsellorPlaybookService $playbooks,
    ) {}

    public function dashboard(User $user, array $filters = []): array
    {
        $leadQuery = Lead::with(['program', 'assignedTo', 'counsellingProfile'])->latest();
        $this->hierarchy->applyLeadVisibility($leadQuery, $user, 'ADM');
        $applicantQuery = Applicant::with(['user', 'program', 'assignedCounsellor', 'counsellingProfile'])->latest();
        $this->hierarchy->applyApplicantVisibility($applicantQuery, $user, 'ADM');

        $assignedLeadIds = (clone $leadQuery)->where('assigned_to', $user->id)->pluck('id');
        $assignedApplicantIds = (clone $applicantQuery)->where('assigned_to', $user->id)->pluck('id');

        $nextBestCalls = (clone $leadQuery)
            ->whereIn('status', ['new', 'contacted', 'interested'])
            ->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 else 3 end")
            ->orderBy('last_contacted_at')
            ->limit(12)
            ->get();

        $applicantBlockers = (clone $applicantQuery)
            ->whereIn('status', ['submitted', 'under_review', 'shortlisted', 'selected'])
            ->where(fn ($q) => $q->whereNull('registration_fee_paid_at')->orWhereHas('documents', fn ($d) => $d->whereIn('status', ['pending', 'rejected'])))
            ->limit(12)
            ->get();

        $assessmentFollowups = AdmissionAssessmentPanelAssignment::with(['panel.session', 'applicant.user'])
            ->whereIn('applicant_id', $assignedApplicantIds)
            ->whereIn('lifecycle_status', ['invited', 'confirmed', 'rescheduled', 'no_show'])
            ->latest('updated_at')
            ->limit(12)
            ->get();

        $reminders = AdmissionReminderSchedule::with('subject')
            ->whereIn('status', ['scheduled', 'queued', 'escalated'])
            ->where(fn ($q) => $q
                ->where(fn ($leadScope) => $leadScope->where('subject_type', Lead::class)->whereIn('subject_id', $assignedLeadIds))
                ->orWhere(fn ($appScope) => $appScope->where('subject_type', Applicant::class)->whereIn('subject_id', $assignedApplicantIds)))
            ->orderBy('due_at')
            ->limit(12)
            ->get();

        $focusSubject = $applicantBlockers->first() ?: $nextBestCalls->first();

        return [
            'stats' => [
                'next_calls' => $nextBestCalls->count(),
                'applicant_blockers' => $applicantBlockers->count(),
                'assessment_followups' => $assessmentFollowups->count(),
                'due_reminders' => $reminders->count(),
            ],
            'nextBestCalls' => $nextBestCalls,
            'applicantBlockers' => $applicantBlockers,
            'assessmentFollowups' => $assessmentFollowups,
            'reminders' => $reminders,
            'timeline' => $focusSubject ? $this->timeline->forSubject($focusSubject, 12) : collect(),
            'playbooks' => $this->playbooks->forSubject($focusSubject),
            'focusSubject' => $focusSubject,
        ];
    }
}
