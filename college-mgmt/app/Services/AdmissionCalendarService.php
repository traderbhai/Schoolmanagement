<?php

namespace App\Services;

use App\Models\AdmissionAssessmentPanel;
use App\Models\AdmissionPayment;
use App\Models\AdmissionReminderSchedule;
use App\Models\AdmissionWalkIn;
use App\Models\LeadFollowUp;
use App\Models\OfferLetter;
use App\Models\SelectionSession;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class AdmissionCalendarService
{
    public function __construct(private AdmissionAccessPolicyService $accessPolicy) {}

    public function eventsFor(User $viewer, array $filters = []): Collection
    {
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(7)->startOfDay();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->addDays(30)->endOfDay();

        $visibleIds = $this->accessPolicy->canSeeAll($viewer)
            ? null
            : $this->accessPolicy->visibleUserIds($viewer)->push($viewer->id)->unique();

        $events = collect();

        LeadFollowUp::with(['lead', 'counsellor'])
            ->whereBetween('scheduled_at', [$from, $to])
            ->when($visibleIds, fn ($q) => $q->whereIn('assigned_to', $visibleIds))
            ->limit(100)
            ->get()
            ->each(fn ($row) => $events->push($this->event('follow_up', $row->lead?->name ?? 'Lead follow-up', $row->scheduled_at, $row->lead ? route('admission.leads.show', $row->lead) : route('admission.calendar.index'), $row->completed_at ? 'done' : 'due')));

        AdmissionReminderSchedule::with('subject')
            ->whereBetween('due_at', [$from, $to])
            ->when($visibleIds, fn ($q) => $q->where(fn ($inner) => $inner->whereIn('assigned_to', $visibleIds)->orWhereIn('owner_user_id', $visibleIds)))
            ->limit(100)
            ->get()
            ->each(fn ($row) => $events->push($this->event('reminder', ucfirst(str_replace('_', ' ', $row->reason)), $row->due_at, route('admission.reminders.index'), $row->status)));

        SelectionSession::with('program')
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
            ->limit(100)
            ->get()
            ->each(fn ($row) => $events->push($this->event('assessment_session', $row->session_name, $row->scheduled_date, route('admission.sessions.show', $row), $row->status)));

        AdmissionAssessmentPanel::with('session')
            ->whereBetween('scheduled_at', [$from, $to])
            ->limit(100)
            ->get()
            ->each(fn ($row) => $events->push($this->event('assessment_panel', $row->name, $row->scheduled_at, route('admission.assessment-panels.index'), $row->status)));

        AdmissionPayment::with('applicant.user')
            ->whereIn('status', ['pending', 'rejected'])
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->limit(100)
            ->get()
            ->each(fn ($row) => $events->push($this->event('payment_due', $row->applicant?->user?->name ?? 'Payment follow-up', $row->payment_date, $row->applicant ? route('admission.applicants.payments', $row->applicant) : route('admission.calendar.index'), $row->status)));

        OfferLetter::with('applicant.user')
            ->whereBetween('acceptance_deadline', [$from->toDateString(), $to->toDateString()])
            ->limit(100)
            ->get()
            ->each(fn ($row) => $events->push($this->event('offer_deadline', $row->applicant?->user?->name ?? 'Offer deadline', $row->acceptance_deadline, route('admission.offer-letters.show', $row), $row->status)));

        AdmissionWalkIn::with('counsellor')
            ->whereBetween('visited_at', [$from, $to])
            ->when($visibleIds, fn ($q) => $q->whereIn('assigned_counsellor_id', $visibleIds))
            ->limit(100)
            ->get()
            ->each(fn ($row) => $events->push($this->event('walk_in', $row->visitor_name, $row->visited_at, route('admission.walk-ins.index'), $row->status)));

        return $events->sortBy('starts_at')->values();
    }

    private function event(string $type, string $title, $startsAt, string $route, string $status): array
    {
        return compact('type', 'title', 'route', 'status') + ['starts_at' => $startsAt];
    }
}
