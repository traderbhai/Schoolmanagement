<?php

namespace App\Services;

use App\Models\AcademicDeanActionItem;
use App\Models\AcademicDeanMeetingMinute;
use App\Models\AcademicDeanReviewMeeting;
use App\Models\User;

class AcademicDeanMinutesService
{
    public function store(User $actor, AcademicDeanReviewMeeting $meeting, array $data): AcademicDeanMeetingMinute
    {
        abort_if(in_array($meeting->status, ['closed', 'cancelled'], true), 422, 'Closed or cancelled Dean review meetings cannot accept new minutes.');

        return AcademicDeanMeetingMinute::updateOrCreate(
            ['meeting_id' => $meeting->id],
            $data + ['submitted_by' => $actor->id, 'status' => $data['status'] ?? 'submitted']
        );
    }

    public function approve(User $actor, AcademicDeanMeetingMinute $minute): AcademicDeanActionItem
    {
        abort_if($minute->status === 'approved', 422, 'Approved Dean meeting minutes cannot be approved again.');
        abort_if(in_array($minute->meeting?->status, ['closed', 'cancelled'], true), 422, 'Minutes for closed or cancelled Dean review meetings cannot be changed.');

        $minute->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
        $minute->meeting?->update(['status' => 'closed']);

        return AcademicDeanActionItem::create([
            'meeting_id' => $minute->meeting_id,
            'title' => 'Follow up approved minutes: ' . ($minute->meeting?->title ?? 'Dean review'),
            'description' => str($minute->minutes)->limit(500),
            'source_type' => 'meeting_minutes',
            'source_key' => (string) $minute->id,
            'owner_user_id' => $actor->id,
            'assigned_by' => $actor->id,
            'priority' => 'normal',
            'due_at' => now()->addDays(7),
            'status' => 'open',
            'metadata' => ['auto_created' => true, 'version' => 'Academics OS v0.08'],
        ]);
    }
}
