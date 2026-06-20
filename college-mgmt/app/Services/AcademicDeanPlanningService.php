<?php

namespace App\Services;

use App\Models\AcademicDeanActionItem;
use App\Models\AcademicDeanPlanningCycle;
use App\Models\AcademicDeanReadinessItem;
use App\Models\User;
use Illuminate\Support\Collection;

class AcademicDeanPlanningService
{
    private const FINAL_STATUSES = ['published', 'closed', 'cancelled'];
    private const ALLOWED_STATUS_TRANSITIONS = [
        'draft' => ['branch_review', 'dean_review', 'approved', 'published', 'cancelled'],
        'branch_review' => ['dean_review', 'approved', 'returned', 'cancelled'],
        'dean_review' => ['approved', 'returned', 'cancelled'],
        'returned' => ['draft', 'branch_review', 'dean_review', 'cancelled'],
        'approved' => ['published', 'revised', 'closed'],
        'revised' => ['dean_review', 'approved', 'cancelled'],
        'published' => ['closed'],
        'closed' => [],
        'cancelled' => [],
    ];

    public const SECTIONS = [
        'curriculum_readiness', 'faculty_allocation', 'timetable_readiness',
        'classroom_lab_readiness', 'lms_material_readiness', 'assessment_plan_readiness',
        'mentoring_readiness', 'admission_handoff_readiness',
    ];

    public function dashboard(): array
    {
        $cycles = AcademicDeanPlanningCycle::with(['program', 'owner', 'readinessItems'])->latest()->paginate(12);
        $items = AcademicDeanReadinessItem::with('planningCycle', 'owner')->where('is_blocker', true)->where('status', '!=', 'done')->orderBy('due_at')->paginate(15);

        return [
            'cycles' => $cycles,
            'blockers' => $items,
            'kpis' => [
                'active_plans' => AcademicDeanPlanningCycle::whereNotIn('status', ['closed', 'cancelled'])->count(),
                'published_calendars' => AcademicDeanPlanningCycle::where('cycle_type', 'academic_calendar')->where('status', 'published')->count(),
                'readiness_blockers' => AcademicDeanReadinessItem::where('is_blocker', true)->where('status', '!=', 'done')->count(),
                'load_approvals' => AcademicDeanPlanningCycle::where('cycle_type', 'teaching_load')->whereIn('status', ['dean_review', 'approved'])->count(),
            ],
        ];
    }

    public function createPlan(User $actor, array $data): AcademicDeanPlanningCycle
    {
        $cycle = AcademicDeanPlanningCycle::create($data + ['owner_user_id' => $data['owner_user_id'] ?? $actor->id]);
        foreach (self::SECTIONS as $section) {
            AcademicDeanReadinessItem::firstOrCreate(
                ['planning_cycle_id' => $cycle->id, 'section' => $section],
                [
                    'title' => str_replace('_', ' ', $section),
                    'owner_user_id' => $cycle->owner_user_id,
                    'status' => $section === 'admission_handoff_readiness' ? 'blocked' : 'pending',
                    'is_blocker' => in_array($section, ['faculty_allocation', 'timetable_readiness', 'admission_handoff_readiness'], true),
                    'due_at' => now()->addDays(7),
                ]
            );
        }
        $this->refreshScore($cycle);

        return $cycle->fresh('readinessItems');
    }

    public function approve(AcademicDeanPlanningCycle $cycle, string $status = 'approved'): AcademicDeanPlanningCycle
    {
        $current = $cycle->status ?: 'draft';
        abort_if(in_array($current, self::FINAL_STATUSES, true) && $status !== 'closed', 422, 'Finalized Dean planning cycles cannot be downgraded or revised in place.');
        abort_unless(in_array($status, self::ALLOWED_STATUS_TRANSITIONS[$current] ?? [], true), 422, "Dean planning cycle cannot move from {$current} to {$status}.");

        $cycle->update(['status' => $status]);
        return $cycle->fresh();
    }

    public function createActionFromBlocker(User $actor, AcademicDeanReadinessItem $item): AcademicDeanActionItem
    {
        return AcademicDeanActionItem::firstOrCreate(
            [
                'source_type' => 'planning_readiness',
                'source_key' => (string) $item->id,
            ],
            [
                'title' => 'Clear readiness blocker: ' . $item->title,
                'description' => 'Created from Dean planning readiness item.',
                'owner_user_id' => $item->owner_user_id,
                'assigned_by' => $actor->id,
                'priority' => $item->is_blocker ? 'high' : 'normal',
                'due_at' => $item->due_at,
                'status' => 'open',
                'metadata' => ['planning_cycle_id' => $item->planning_cycle_id, 'readiness_section' => $item->section, 'version' => 'Academics OS v0.08'],
            ]
        );
    }

    public function refreshScore(AcademicDeanPlanningCycle $cycle): int
    {
        $items = $cycle->readinessItems()->get();
        $done = $items->where('status', 'done')->count();
        $score = $items->count() ? (int) round(($done / $items->count()) * 100) : 0;
        $cycle->update(['readiness_score' => $score]);
        return $score;
    }
}
