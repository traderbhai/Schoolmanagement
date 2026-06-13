<?php

namespace App\Services;

use App\Models\AdmissionAssignmentEvent;
use App\Models\AdmissionAssignmentRule;
use App\Models\Applicant;
use App\Models\DepartmentMember;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AdmissionAssignmentService
{
    public function __construct(
        private DepartmentHierarchyService $hierarchy,
        private AdmissionWorkflowService $workflow,
    ) {}

    public function assignLead(Lead $lead, User $to, User $actor, array $options = []): Lead
    {
        return $this->assign($lead, $to, $actor, $options);
    }

    public function assignApplicant(Applicant $applicant, User $to, User $actor, array $options = []): Applicant
    {
        return $this->assign($applicant, $to, $actor, $options);
    }

    public function delegate(Lead|Applicant $subject, User $to, User $actor, array $options = []): Lead|Applicant
    {
        return $this->assign($subject, $to, $actor, array_merge($options, ['mode' => $options['mode'] ?? 'delegated']));
    }

    public function autoAssignLead(Lead $lead, User $actor): ?Lead
    {
        $rule = AdmissionAssignmentRule::where('object_type', 'lead')
            ->where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->first(fn (AdmissionAssignmentRule $rule) => $this->ruleMatches($rule, $lead));

        $assignee = $rule ? $this->resolveRuleAssignee($rule, $actor, $lead) : null;
        $assignee = $assignee ?: $this->leastLoadedEligibleAssignee($actor, 'lead');

        return $assignee
            ? $this->assignLead($lead, $assignee, $actor, ['mode' => 'auto', 'reason' => $rule?->name ?? 'Fallback assignment'])
            : null;
    }

    public function bulkAssign(string $type, array $ids, User $to, User $actor, array $options = []): int
    {
        $model = $type === 'applicant' ? Applicant::class : Lead::class;
        $count = 0;

        $model::whereIn('id', $ids)->get()->each(function (Lead|Applicant $subject) use ($to, $actor, $options, &$count) {
            $this->assign($subject, $to, $actor, $options + ['mode' => 'bulk_assign']);
            $count++;
        });

        return $count;
    }

    public function bulkReassign(string $type, array $ids, User $to, User $actor, array $options = []): int
    {
        return $this->bulkAssign($type, $ids, $to, $actor, $options + ['mode' => 'bulk_reassign']);
    }

    public function eligibleAssigneesFor(User $actor, string $type = 'lead'): Collection
    {
        if ($actor->hasRole('admin') || $this->hierarchy->canSeeAll($actor, 'ADM')) {
            return $this->activeAdmissionMembers()->pluck('user')->filter()->unique('id')->values();
        }

        $visibleIds = $this->hierarchy->visibleUserIds($actor, 'ADM');

        return User::whereIn('id', $visibleIds)->orderBy('name')->get();
    }

    public function assign(Lead|Applicant $subject, User $to, User $actor, array $options = []): Lead|Applicant
    {
        if ((int) $to->id !== (int) $actor->id && !$this->hierarchy->canAssignTo($actor, $to, 'ADM')) {
            abort(403, 'You cannot assign outside your admission hierarchy scope.');
        }

        $previousUserId = $subject->assigned_to;
        $priority = $options['priority'] ?? $subject->priority ?? 'normal';
        $slaDueAt = $options['sla_due_at'] ?? $subject->sla_due_at ?? $this->workflow->slaDueAt($subject, $priority);

        $subject->update([
            'assigned_to' => $to->id,
            'current_handler_user_id' => $to->id,
            'owner_user_id' => $subject->owner_user_id ?: $to->id,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
            'assignment_reason' => $options['reason'] ?? $options['assignment_reason'] ?? null,
            'assignment_mode' => $options['mode'] ?? 'manual',
            'priority' => $priority,
            'sla_due_at' => $slaDueAt,
            'next_action' => $options['next_action'] ?? $subject->next_action,
            'last_activity_at' => now(),
            'team' => $options['team'] ?? ($subject instanceof Lead ? $subject->team : null),
            'region' => $options['region'] ?? ($subject instanceof Lead ? $subject->region : null),
        ]);

        $event = AdmissionAssignmentEvent::create([
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'from_user_id' => $previousUserId,
            'to_user_id' => $to->id,
            'assigned_by' => $actor->id,
            'mode' => $options['mode'] ?? 'manual',
            'reason' => $options['reason'] ?? $options['assignment_reason'] ?? null,
            'notes' => $options['notes'] ?? null,
            'sla_before' => $subject->getOriginal('sla_due_at'),
            'sla_after' => $slaDueAt,
            'metadata' => ['priority' => $priority],
        ]);

        $this->hierarchy->recordActivity(
            'ADM',
            $actor,
            $subject instanceof Lead ? 'lead_assigned' : 'applicant_assigned',
            'Assigned ' . class_basename($subject) . ' #' . $subject->id . ' to ' . $to->name . '.',
            $event,
            $to,
            ['subject_type' => get_class($subject), 'subject_id' => $subject->id]
        );

        return $subject->fresh();
    }

    private function activeAdmissionMembers(): Collection
    {
        return DepartmentMember::with(['user', 'role'])
            ->whereHas('department', fn ($q) => $q->where('code', 'ADM'))
            ->where('is_active', true)
            ->get()
            ->filter(fn (DepartmentMember $member) => $member->user && !$member->user->hasRole('admin'));
    }

    private function ruleMatches(AdmissionAssignmentRule $rule, Lead|Applicant $subject): bool
    {
        foreach (($rule->conditions ?? []) as $field => $expected) {
            if ($expected === null || $expected === '') {
                continue;
            }
            $actual = match ($field) {
                'program', 'program_id' => $subject->program_id,
                'batch', 'batch_id' => $subject instanceof Applicant ? $subject->batch_id : null,
                'lead_status', 'applicant_status', 'status' => $subject->status,
                default => $subject->{$field} ?? null,
            };
            if (is_array($expected) ? !in_array($actual, $expected) : (string) $actual !== (string) $expected) {
                return false;
            }
        }

        return true;
    }

    private function resolveRuleAssignee(AdmissionAssignmentRule $rule, User $actor, Lead|Applicant $subject): ?User
    {
        if ($rule->assignee_strategy === 'fixed_user' && $rule->targetUser) {
            return $rule->targetUser;
        }

        $members = $this->activeAdmissionMembers();
        if ($rule->target_team_id) {
            $members = $members->where('department_team_id', $rule->target_team_id);
        }
        if ($rule->target_role_id) {
            $members = $members->where('department_role_id', $rule->target_role_id);
        }

        $eligible = $this->eligibleAssigneesFor($actor)->pluck('id');
        $users = $members->pluck('user')->filter()->whereIn('id', $eligible)->values();

        return $rule->assignee_strategy === 'least_workload'
            ? $this->leastLoadedUser($users, $rule->object_type)
            : $users->sortBy(fn (User $user) => $this->workload($user, $rule->object_type))->first();
    }

    private function leastLoadedEligibleAssignee(User $actor, string $type): ?User
    {
        return $this->leastLoadedUser($this->eligibleAssigneesFor($actor, $type), $type);
    }

    private function leastLoadedUser(Collection $users, string $type): ?User
    {
        return $users->sortBy(fn (User $user) => $this->workload($user, $type))->first();
    }

    private function workload(User $user, string $type): int
    {
        return $type === 'applicant'
            ? Applicant::where('assigned_to', $user->id)->whereNotIn('status', ['withdrawn', 'rejected'])->count()
            : Lead::where('assigned_to', $user->id)->whereNotIn('status', ['converted', 'not_interested'])->count();
    }
}
