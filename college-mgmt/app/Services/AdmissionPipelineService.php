<?php

namespace App\Services;

use App\Models\AdmissionPipelineBoard;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Collection;

class AdmissionPipelineService
{
    private const LEAD_STAGES = ['new', 'contacted', 'interested', 'not_interested', 'converted'];
    private const APPLICANT_STAGES = ['draft', 'submitted', 'under_review', 'shortlisted', 'selected', 'rejected', 'withdrawn'];

    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function board(string $objectType = 'lead'): AdmissionPipelineBoard
    {
        return AdmissionPipelineBoard::firstOrCreate(
            ['object_type' => $objectType, 'is_default' => true],
            [
                'name' => $objectType === 'lead' ? 'Lead Pipeline' : 'Applicant Pipeline',
                'columns' => $objectType === 'lead' ? self::LEAD_STAGES : self::APPLICANT_STAGES,
            ]
        );
    }

    public function snapshot(User $viewer, string $objectType = 'lead', array $filters = []): array
    {
        $board = $this->board($objectType);
        $query = $objectType === 'lead' ? Lead::with(['program', 'assignedTo']) : Applicant::with(['user', 'program', 'assignedCounsellor']);
        $objectType === 'lead'
            ? $this->hierarchy->applyLeadVisibility($query, $viewer, 'ADM')
            : $this->hierarchy->applyApplicantVisibility($query, $viewer, 'ADM');

        $records = $query
            ->when($filters['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->latest()
            ->limit(250)
            ->get();

        return [
            'board' => $board,
            'columns' => collect($board->columns)->mapWithKeys(fn ($stage) => [
                $stage => $records->where('status', $stage)->values(),
            ]),
        ];
    }

    public function move(Lead|Applicant $subject, string $targetStage, User $actor, ?string $reason = null): Lead|Applicant
    {
        $allowed = $subject instanceof Lead ? self::LEAD_STAGES : self::APPLICANT_STAGES;
        abort_unless(in_array($targetStage, $allowed, true), 422, 'Unknown pipeline stage.');
        abort_if($subject instanceof Lead && $subject->status === 'converted' && $targetStage !== 'converted', 422, 'Converted leads cannot move backward.');
        abort_if($subject instanceof Applicant && $subject->status === 'selected' && $targetStage === 'draft', 422, 'Selected applicants cannot return to draft.');

        $subject->update([
            'status' => $targetStage,
            'last_activity_at' => now(),
            'next_action' => $reason ?: 'Pipeline moved to ' . str_replace('_', ' ', $targetStage),
        ]);

        return $subject->fresh();
    }

    public function allowedStages(string $objectType): Collection
    {
        return collect($objectType === 'lead' ? self::LEAD_STAGES : self::APPLICANT_STAGES);
    }
}
