<?php

namespace App\Services;

use App\Models\AdmissionManagerReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AdmissionManagerReviewService
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function create(Model $subject, array $data, ?User $actor = null): AdmissionManagerReview
    {
        return AdmissionManagerReview::create([
            'reviewable_type' => get_class($subject),
            'reviewable_id' => $subject->getKey(),
            'review_type' => $data['review_type'] ?? 'note_quality',
            'status' => $data['status'] ?? 'pending',
            'severity' => $data['severity'] ?? 'normal',
            'assigned_manager_id' => $data['assigned_manager_id'] ?? $actor?->id,
            'finding' => $data['finding'] ?? null,
            'action_required' => $data['action_required'] ?? null,
            'due_at' => $data['due_at'] ?? now()->addDays(2),
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    public function queueFor(User $viewer, array $filters = []): Collection
    {
        $query = AdmissionManagerReview::with(['reviewable', 'manager'])
            ->when($filters['review_type'] ?? null, fn ($q, $type) => $q->where('review_type', $type))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw("case severity when 'critical' then 1 when 'high' then 2 when 'normal' then 3 else 4 end")
            ->orderBy('due_at');

        if (!$viewer->hasRole('admin') && !$this->hierarchy->canSeeAll($viewer, 'ADM')) {
            $visibleIds = $this->hierarchy->visibleUserIds($viewer, 'ADM')->push($viewer->id)->unique();
            $query->whereIn('assigned_manager_id', $visibleIds);
        }

        return $query->limit(100)->get();
    }

    public function resolve(AdmissionManagerReview $review, User $actor, string $notes): AdmissionManagerReview
    {
        $review->update([
            'status' => 'resolved',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'resolution_notes' => $notes,
        ]);

        return $review->refresh();
    }
}
