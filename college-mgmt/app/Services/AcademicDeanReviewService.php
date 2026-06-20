<?php

namespace App\Services;

use App\Models\AcademicDeanActionItem;
use App\Models\AcademicDeanReviewMeeting;
use App\Models\User;

class AcademicDeanReviewService
{
    public function __construct(private AcademicHierarchyService $hierarchy) {}

    public function createMeeting(User $actor, array $data): AcademicDeanReviewMeeting
    {
        $meeting = AcademicDeanReviewMeeting::create($data + ['chaired_by' => $actor->id]);
        $this->record($actor, 'academic_dean_review_created', 'Created Dean review meeting: ' . $meeting->title, $meeting);

        return $meeting;
    }

    public function createAction(User $actor, array $data): AcademicDeanActionItem
    {
        $action = AcademicDeanActionItem::create($data + ['assigned_by' => $actor->id]);
        $this->record($actor, 'academic_dean_action_created', 'Created Dean action item: ' . $action->title, $action, $action->owner);

        return $action;
    }

    public function updateAction(User $actor, AcademicDeanActionItem $action, array $data): AcademicDeanActionItem
    {
        if (($data['status'] ?? null) === 'done') {
            $closureNote = trim((string) ($data['closure_note'] ?? ''));
            abort_if($closureNote === '' && ! $action->evidence()->exists(), 422, 'Dean action closure requires evidence or a closure note.');
            $data['closure_note'] = $closureNote;
        }

        if (($data['status'] ?? null) === 'done' && ! $action->closed_at) {
            $data['closed_at'] = now();
        }

        $action->update($data);
        $this->record($actor, 'academic_dean_action_updated', 'Updated Dean action item: ' . $action->title, $action, $action->owner);

        return $action->fresh();
    }

    private function record(User $actor, string $action, string $description, mixed $subject, ?User $target = null): void
    {
        $this->hierarchy->record($actor, $action, $description, $subject, $target, ['version' => 'Academics OS v0.07']);
    }
}
