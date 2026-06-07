<?php

namespace App\Services;

use App\Models\ApprovalWorkflow;

class ApprovalChainService
{
    /**
     * Chain definitions: workflow_type => ordered list of steps.
     * Each step: [approver_role, sla_hours, label]
     */
    private static array $chains = [
        'offer_letter' => [
            ['role' => 'hod',              'sla_hours' => 24,  'label' => 'HOD Clearance'],
            ['role' => 'dean_academics',   'sla_hours' => 48,  'label' => 'Dean Approval'],
            ['role' => 'program_chair',    'sla_hours' => 24,  'label' => 'Chair Final Sign-off'],
        ],
        'leave_request' => [
            ['role' => 'hod',              'sla_hours' => 24,  'label' => 'HOD Approval'],
            ['role' => 'dean_academics',   'sla_hours' => 48,  'label' => 'Dean Sign-off'],
        ],
        'curriculum_change' => [
            ['role' => 'hod',              'sla_hours' => 72,  'label' => 'HOD Review'],
            ['role' => 'program_chair',    'sla_hours' => 72,  'label' => 'Chair Approval'],
            ['role' => 'dean_academics',   'sla_hours' => 96,  'label' => 'Dean Final Approval'],
        ],
        'document_verification' => [
            ['role' => 'admission_officer','sla_hours' => 24,  'label' => 'Officer Verification'],
            ['role' => 'admission_head',   'sla_hours' => 48,  'label' => 'Head Clearance'],
        ],
        'general' => [
            ['role' => 'hod',              'sla_hours' => 48,  'label' => 'HOD Approval'],
            ['role' => 'dean_academics',   'sla_hours' => 72,  'label' => 'Dean Approval'],
        ],
    ];

    public static function getChain(string $workflowType): array
    {
        return self::$chains[$workflowType] ?? self::$chains['general'];
    }

    /**
     * Create the first pending step for a workflow chain.
     */
    public static function initiate(
        object $approvable,
        string $workflowType,
        ?int $parentId = null
    ): ApprovalWorkflow {
        $steps = self::getChain($workflowType);
        $firstStep = $steps[0];

        return ApprovalWorkflow::create([
            'approvable_type'    => get_class($approvable),
            'approvable_id'      => $approvable->id,
            'approver_role'      => $firstStep['role'],
            'status'             => 'pending',
            'step_order'         => 1,
            'workflow_type'      => $workflowType,
            'sla_hours'          => $firstStep['sla_hours'],
            'due_at'             => now()->addHours($firstStep['sla_hours']),
            'parent_approval_id' => $parentId,
        ]);
    }

    /**
     * After a step is approved, advance to the next step if one exists.
     * Returns the next ApprovalWorkflow, or null if the chain is complete.
     */
    public static function advance(ApprovalWorkflow $approval): ?ApprovalWorkflow
    {
        $steps = self::getChain($approval->workflow_type ?? 'general');
        $nextStepIndex = $approval->step_order; // 0-based index = step_order (1-based) gives next

        if (!isset($steps[$nextStepIndex])) {
            return null; // chain complete
        }

        $nextStep = $steps[$nextStepIndex];

        return ApprovalWorkflow::create([
            'approvable_type'    => $approval->approvable_type,
            'approvable_id'      => $approval->approvable_id,
            'approver_role'      => $nextStep['role'],
            'status'             => 'pending',
            'step_order'         => $approval->step_order + 1,
            'workflow_type'      => $approval->workflow_type,
            'sla_hours'          => $nextStep['sla_hours'],
            'due_at'             => now()->addHours($nextStep['sla_hours']),
            'parent_approval_id' => $approval->id,
        ]);
    }

    /**
     * Get all steps for a given approvable, in order.
     */
    public static function getHistory(object $approvable): \Illuminate\Database\Eloquent\Collection
    {
        return ApprovalWorkflow::where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->with('approver')
            ->orderBy('step_order')
            ->get();
    }

    /**
     * Check if a chain is fully approved (all steps approved).
     */
    public static function isFullyApproved(object $approvable, string $workflowType): bool
    {
        $steps = self::getChain($workflowType);
        $approvedCount = ApprovalWorkflow::where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->where('workflow_type', $workflowType)
            ->where('status', 'approved')
            ->count();

        return $approvedCount >= count($steps);
    }

    /**
     * Escalate overdue pending approvals to the next role in the chain.
     * Called by the console command.
     */
    public static function escalateOverdue(): int
    {
        $count = 0;
        $overdue = ApprovalWorkflow::where('status', 'pending')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNull('escalated_at')
            ->get();

        foreach ($overdue as $approval) {
            $steps = self::getChain($approval->workflow_type ?? 'general');
            $nextStepIndex = $approval->step_order; // points to next step definition

            $escalateTo = $steps[$nextStepIndex]['role']
                ?? $steps[count($steps) - 1]['role']
                ?? 'admin';

            $approval->update([
                'escalated_at'      => now(),
                'escalated_to_role' => $escalateTo,
            ]);

            $count++;
        }

        return $count;
    }

    public static function getChainDefinitions(): array
    {
        return self::$chains;
    }
}
