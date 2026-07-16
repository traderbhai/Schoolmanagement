<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionAssignmentRule;
use App\Models\DepartmentRole;
use App\Models\DepartmentTeam;
use App\Models\User;
use App\Services\AdmissionAccessPolicyService;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;

class AssignmentRuleController extends Controller
{
    public function __construct(private AdmissionAccessPolicyService $policy) {}

    public function index(Request $request)
    {
        $this->policy->authorizePermission($request->user(), 'assign_work');

        $rules = AdmissionAssignmentRule::with(['targetUser', 'targetTeam', 'targetRole'])
            ->orderBy('priority')
            ->paginate(25);
        $users = User::whereHas('roles', fn ($q) => $q->whereIn('name', DepartmentHierarchyService::ADMISSION_ROLE_NAMES))->orderBy('name')->get();
        $teams = DepartmentTeam::whereHas('department', fn ($q) => $q->where('code', 'ADM'))->where('is_active', true)->orderBy('name')->get();
        $roles = DepartmentRole::whereHas('department', fn ($q) => $q->where('code', 'ADM'))->where('is_active', true)->orderBy('level')->get();

        return view('admission.assignment-rules.index', compact('rules', 'users', 'teams', 'roles'));
    }

    public function store(Request $request)
    {
        $this->policy->authorizePermission($request->user(), 'assign_work');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'object_type' => ['required', 'in:lead,applicant'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'assignee_strategy' => ['required', 'in:fixed_user,fixed_team,role_under_manager,least_workload,round_robin,keep_current_level'],
            'target_user_id' => ['nullable', 'exists:users,id'],
            'target_team_id' => ['nullable', 'exists:department_teams,id'],
            'target_role_id' => ['nullable', 'exists:department_roles,id'],
            'fallback_strategy' => ['nullable', 'in:least_workload,round_robin,keep_current_level'],
            'conditions' => ['nullable', 'array'],
        ]);

        AdmissionAssignmentRule::updateOrCreate(
            ['id' => $request->integer('rule_id') ?: null],
            $validated + ['is_active' => $request->boolean('is_active', true)]
        );

        return back()->with('success', 'Assignment rule saved.');
    }

    public function toggle(Request $request, AdmissionAssignmentRule $rule)
    {
        $this->policy->authorizePermission($request->user(), 'assign_work');
        $rule->update(['is_active' => !$rule->is_active]);

        return back()->with('success', 'Assignment rule updated.');
    }
}
