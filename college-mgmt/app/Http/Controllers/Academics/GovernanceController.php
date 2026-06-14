<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Models\AcademicScopeAssignment;
use App\Models\Batch;
use App\Models\DepartmentMember;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Term;
use App\Services\AcademicAccessPolicyService;
use App\Services\AcademicHierarchyService;
use App\Services\AcademicScopeService;
use Illuminate\Http\Request;

class GovernanceController extends Controller
{
    public function __construct(
        private AcademicAccessPolicyService $policy,
        private AcademicHierarchyService $hierarchy,
        private AcademicScopeService $scopes
    ) {}

    public function index(Request $request)
    {
        $this->policy->authorizeRead($request->user());

        $department = $this->hierarchy->department();
        $canConfigure = $this->policy->canConfigureGovernance($request->user());
        $branches = $this->hierarchy->branches();
        $roles = $this->hierarchy->roles();
        $members = $this->hierarchy->members();
        $scopeAssignments = AcademicScopeAssignment::with(['user', 'member.role', 'member.team', 'assignedBy'])
            ->whereIn('user_id', $members->pluck('user_id'))
            ->currentlyActive()
            ->orderBy('scope_type')
            ->orderBy('scope_name')
            ->get();
        $activityLogs = $department->activityLogs()
            ->with(['actor', 'target'])
            ->latest()
            ->limit(30)
            ->get();
        $programs = Program::where('is_active', true)->orderBy('name')->get();
        $batches = Batch::with('program')->orderBy('name')->get();
        $terms = Term::with(['program', 'batch'])->orderBy('term_number')->get();
        $subjects = Subject::with('program')->where('is_active', true)->orderBy('name')->get();

        $scopeCatalog = $this->scopeCatalog($branches, $programs, $batches, $terms, $subjects);

        return view('academics.governance.index', compact(
            'department',
            'canConfigure',
            'branches',
            'roles',
            'members',
            'scopeAssignments',
            'activityLogs',
            'programs',
            'batches',
            'terms',
            'subjects',
            'scopeCatalog'
        ));
    }

    public function storeScope(Request $request)
    {
        $this->policy->authorizeGovernance($request->user());

        $validated = $request->validate([
            'department_member_id' => ['required', 'exists:department_members,id'],
            'scope_type' => ['required', 'in:' . implode(',', AcademicScopeService::SCOPE_TYPES)],
            'scope_id' => ['nullable', 'integer'],
            'scope_code' => ['nullable', 'string', 'max:100'],
            'scope_name' => ['required', 'string', 'max:255'],
            'context' => ['nullable', 'string', 'max:100'],
            'can_manage' => ['nullable', 'boolean'],
        ]);

        $member = DepartmentMember::with(['department', 'user'])->findOrFail($validated['department_member_id']);
        abort_unless($member->department?->code === AcademicHierarchyService::DEPARTMENT_CODE, 422);

        $this->scopes->assign(
            $request->user(),
            $member,
            $validated['scope_type'],
            $validated['scope_id'] ?? null,
            $validated['scope_code'] ?? null,
            $validated['scope_name'],
            $validated['context'] ?? null,
            $request->boolean('can_manage')
        );

        return back()->with('success', 'Academic scope assigned.');
    }

    public function deactivateScope(Request $request, AcademicScopeAssignment $scope)
    {
        $this->policy->authorizeGovernance($request->user());
        $this->scopes->deactivate($request->user(), $scope);

        return back()->with('success', 'Academic scope deactivated.');
    }

    public function hierarchy(Request $request)
    {
        return $this->index($request);
    }

    public function scopes(Request $request)
    {
        return $this->index($request);
    }

    public function permissionMatrix(Request $request)
    {
        return $this->index($request);
    }

    private function scopeCatalog($branches, $programs, $batches, $terms, $subjects): array
    {
        return [
            'branch' => $branches->map(fn ($branch) => [
                'id' => $branch->id,
                'code' => $branch->type,
                'name' => $branch->name,
                'context' => 'department_branch',
            ])->values(),
            'program' => $programs->map(fn ($program) => [
                'id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
                'context' => 'program',
            ])->values(),
            'batch' => $batches->map(fn ($batch) => [
                'id' => $batch->id,
                'code' => $batch->code,
                'name' => $batch->name . ' - ' . ($batch->program?->code ?? 'Program'),
                'context' => 'batch',
            ])->values(),
            'term' => $terms->map(fn ($term) => [
                'id' => $term->id,
                'code' => 'TERM-' . $term->id,
                'name' => $term->name . ' - ' . ($term->program?->code ?? $term->batch?->program?->code ?? 'Program'),
                'context' => 'term',
            ])->values(),
            'semester' => $terms->map(fn ($term) => [
                'id' => $term->id,
                'code' => 'SEM-' . $term->term_number,
                'name' => 'Semester ' . $term->term_number . ' - ' . ($term->program?->code ?? $term->batch?->program?->code ?? 'Program'),
                'context' => 'semester',
            ])->values(),
            'course' => $subjects->map(fn ($subject) => [
                'id' => $subject->id,
                'code' => $subject->code,
                'name' => $subject->name . ' - ' . ($subject->program?->code ?? 'Program'),
                'context' => 'course',
            ])->values(),
            'subject' => $subjects->map(fn ($subject) => [
                'id' => $subject->id,
                'code' => $subject->code,
                'name' => $subject->name . ' - ' . ($subject->program?->code ?? 'Program'),
                'context' => 'subject',
            ])->values(),
            'cohort' => $batches->map(fn ($batch) => [
                'id' => $batch->id,
                'code' => 'COHORT-' . $batch->code,
                'name' => $batch->name . ' Cohort',
                'context' => 'cohort',
            ])->values(),
        ];
    }
}
