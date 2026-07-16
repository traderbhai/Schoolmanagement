<?php

namespace App\Services;

use App\Models\AdmissionAssignmentEvent;
use App\Models\Lead;
use App\Models\User;

class AdmissionDuplicateMergeService
{
    public function __construct(
        private AdmissionAccessPolicyService $accessPolicy,
        private DepartmentHierarchyService $hierarchy,
    ) {}

    public function merge(Lead $primary, Lead $duplicate, User $actor): Lead
    {
        abort_if($primary->id === $duplicate->id, 422, 'Choose two different leads.');
        $this->accessPolicy->authorizeViewAssignedUser($actor, $primary->assigned_to, true);
        $this->accessPolicy->authorizeViewAssignedUser($actor, $duplicate->assigned_to, true);

        $duplicate->followUps()->update(['lead_id' => $primary->id]);
        AdmissionAssignmentEvent::where('subject_type', Lead::class)
            ->where('subject_id', $duplicate->id)
            ->update(['subject_id' => $primary->id]);

        $notes = collect([$primary->notes, $duplicate->notes ? 'Merged duplicate #' . $duplicate->id . ': ' . $duplicate->notes : null])
            ->filter()
            ->join("\n\n");

        $primary->update([
            'phone' => $primary->phone ?: $duplicate->phone,
            'notes' => $notes,
            'last_activity_at' => now(),
        ]);

        $duplicate->update([
            'status' => 'not_interested',
            'notes' => trim(($duplicate->notes ?? '') . "\nMerged into lead #" . $primary->id),
            'last_activity_at' => now(),
        ]);

        $this->hierarchy->recordActivity('ADM', $actor, 'lead_merged', 'Merged duplicate lead #' . $duplicate->id . ' into #' . $primary->id . '.', $primary, null, [
            'duplicate_lead_id' => $duplicate->id,
        ]);

        return $primary->fresh();
    }
}
