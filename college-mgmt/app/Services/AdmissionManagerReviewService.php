<?php

namespace App\Services;

use App\Models\AdmissionManagerReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
        return $this->queryFor($viewer, $filters)->limit(100)->get();
    }

    public function queryFor(User $viewer, array $filters = []): Builder
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

        return $query;
    }

    public function canAccess(AdmissionManagerReview $review, User $viewer): bool
    {
        if ($viewer->hasRole('admin') || $this->hierarchy->canSeeAll($viewer, 'ADM')) {
            return true;
        }

        $visibleIds = $this->hierarchy->visibleUserIds($viewer, 'ADM')->push($viewer->id)->unique();

        return $visibleIds->contains($review->assigned_manager_id);
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
