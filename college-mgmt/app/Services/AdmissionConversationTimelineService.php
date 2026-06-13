<?php

namespace App\Services;

use App\Models\AdmissionAssessmentLifecycleEvent;
use App\Models\AdmissionCallLog;
use App\Models\AdmissionCommunicationLog;
use App\Models\AdmissionConversationEvent;
use App\Models\AdmissionManagerReview;
use App\Models\AdmissionPayment;
use App\Models\AdmissionReminderSchedule;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\CounsellingLog;
use App\Models\Lead;
use Illuminate\Support\Collection;

class AdmissionConversationTimelineService
{
    public function forSubject(Lead|Applicant $subject, int $limit = 30): Collection
    {
        $type = get_class($subject);
        $id = $subject->id;

        $events = collect();

        $events = $events->merge(AdmissionConversationEvent::where('subject_type', $type)->where('subject_id', $id)->get()->map(fn ($event) => [
            'type' => $event->event_type,
            'title' => $event->title,
            'body' => $event->body,
            'at' => $event->occurred_at,
            'icon' => 'chat-square-text',
        ]));

        $events = $events->merge(AdmissionCallLog::where('subject_type', $type)->where('subject_id', $id)->get()->map(fn ($call) => [
            'type' => 'call',
            'title' => ucfirst(str_replace('_', ' ', $call->disposition)),
            'body' => $call->notes,
            'at' => $call->called_at,
            'icon' => 'telephone',
        ]));

        $events = $events->merge(AdmissionCommunicationLog::where('subject_type', $type)->where('subject_id', $id)->get()->map(fn ($log) => [
            'type' => 'communication',
            'title' => strtoupper($log->channel) . ' - ' . $log->status,
            'body' => $log->body,
            'at' => $log->sent_at ?? $log->queued_at ?? $log->created_at,
            'icon' => 'envelope',
        ]));

        $events = $events->merge(AdmissionReminderSchedule::where('subject_type', $type)->where('subject_id', $id)->get()->map(fn ($reminder) => [
            'type' => 'reminder',
            'title' => ucfirst(str_replace('_', ' ', $reminder->reason)) . ' - ' . $reminder->status,
            'body' => $reminder->notes,
            'at' => $reminder->due_at ?? $reminder->created_at,
            'icon' => 'bell',
        ]));

        $events = $events->merge(AdmissionManagerReview::where('reviewable_type', $type)->where('reviewable_id', $id)->get()->map(fn ($review) => [
            'type' => 'manager_review',
            'title' => ucfirst(str_replace('_', ' ', $review->review_type)) . ' - ' . $review->status,
            'body' => $review->finding,
            'at' => $review->due_at ?? $review->created_at,
            'icon' => 'clipboard-check',
        ]));

        if ($subject instanceof Applicant) {
            $events = $events->merge(CounsellingLog::where('applicant_id', $id)->get()->map(fn ($log) => [
                'type' => 'counselling',
                'title' => ucfirst($log->interaction_type) . ' - ' . ucfirst(str_replace('_', ' ', $log->outcome)),
                'body' => $log->notes,
                'at' => $log->created_at,
                'icon' => 'person-lines-fill',
            ]));
            $events = $events->merge(ApplicantDocument::where('applicant_id', $id)->get()->map(fn ($doc) => [
                'type' => 'document',
                'title' => ($doc->original_name ?? 'Document') . ' - ' . $doc->status,
                'body' => $doc->rejection_reason,
                'at' => $doc->uploaded_at ?? $doc->updated_at,
                'icon' => 'folder',
            ]));
            $events = $events->merge(AdmissionPayment::where('applicant_id', $id)->get()->map(fn ($payment) => [
                'type' => 'payment',
                'title' => 'Payment - ' . $payment->status,
                'body' => $payment->formatted_amount ?? null,
                'at' => $payment->verified_at ?? $payment->payment_date ?? $payment->created_at,
                'icon' => 'credit-card',
            ]));
            $events = $events->merge(AdmissionAssessmentLifecycleEvent::where('applicant_id', $id)->get()->map(fn ($event) => [
                'type' => 'assessment',
                'title' => 'Assessment ' . str_replace('_', ' ', $event->to_status),
                'body' => $event->reason ?: $event->notes,
                'at' => $event->created_at,
                'icon' => 'clipboard-data',
            ]));
        }

        return $events->filter(fn ($event) => $event['at'])
            ->sortByDesc('at')
            ->take($limit)
            ->values();
    }
}
