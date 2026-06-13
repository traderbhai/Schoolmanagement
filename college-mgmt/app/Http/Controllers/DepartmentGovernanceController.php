<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentImpersonationSession;
use App\Models\DepartmentMember;
use App\Services\DepartmentHierarchyService;
use App\Support\DashboardRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DepartmentGovernanceController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function index(Request $request)
    {
        $departments = $this->hierarchy->manageableGovernanceDepartments($request->user());
        abort_if($departments->isEmpty(), 403);

        $department = $request->integer('department_id')
            ? Department::findOrFail($request->integer('department_id'))
            : $departments->first();

        abort_unless($this->hierarchy->canAccessDepartmentGovernance($request->user(), $department), 403);

        $features = $this->hierarchy->featureRows($department);
        $members = $department->departmentMembers()
            ->with(['user', 'role', 'team', 'manager.user'])
            ->where('is_active', true)
            ->get()
            ->sortBy('role.level');
        $impersonatableMembers = $this->hierarchy->impersonatableMembers($request->user(), $department);
        $activityLogs = $department->activityLogs()
            ->with(['actor', 'target'])
            ->latest()
            ->limit(25)
            ->get();
        $canManageSettings = $this->hierarchy->canManageDepartmentSettings($request->user(), $department);

        return view('department-governance.index', compact(
            'departments', 'department', 'features', 'members',
            'impersonatableMembers', 'activityLogs', 'canManageSettings'
        ));
    }

    public function updateFeature(Request $request, Department $department)
    {
        abort_unless($this->hierarchy->canManageDepartmentSettings($request->user(), $department), 403);

        $validated = $request->validate([
            'feature_key' => ['required', 'string', 'max:100'],
            'feature_name' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $this->hierarchy->setFeature(
            $request->user(),
            $department,
            $validated['feature_key'],
            $validated['feature_name'] ?? $validated['feature_key'],
            $request->boolean('is_enabled')
        );

        return back()->with('success', 'Department feature setting updated.');
    }

    public function startImpersonation(Request $request, DepartmentMember $member)
    {
        abort_if($request->session()->has('impersonation.original_user_id'), 403, 'Stop the current impersonation before starting another one.');

        $member->load(['department', 'user']);
        abort_unless($this->hierarchy->canImpersonate($request->user(), $member->user_id, $member->department), 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $session = DepartmentImpersonationSession::create([
            'department_id' => $member->department_id,
            'actor_user_id' => $request->user()->id,
            'target_user_id' => $member->user_id,
            'started_at' => now(),
            'ip_address' => $request->ip(),
            'reason' => $validated['reason'] ?? null,
        ]);

        $this->hierarchy->recordActivity(
            $member->department,
            $request->user(),
            'impersonation_started',
            'Started impersonating ' . $member->user->name . '.',
            $session,
            $member->user
        );

        $request->session()->put('impersonation.original_user_id', $request->user()->id);
        $request->session()->put('impersonation.session_id', $session->id);
        Auth::loginUsingId($member->user_id);
        $request->session()->regenerate();

        return redirect(DashboardRedirect::forUser(Auth::user()))
            ->with('success', 'You are now impersonating ' . $member->user->name . '.');
    }

    public function stopImpersonation(Request $request)
    {
        $originalUserId = $request->session()->get('impersonation.original_user_id');
        abort_unless($originalUserId, 403);

        $session = DepartmentImpersonationSession::find($request->session()->get('impersonation.session_id'));
        if ($session && !$session->ended_at) {
            $session->update(['ended_at' => now()]);
            $this->hierarchy->recordActivity(
                $session->department,
                $session->actor,
                'impersonation_stopped',
                'Stopped impersonating ' . ($session->target?->name ?? 'user') . '.',
                $session,
                $session->target
            );
        }

        Auth::loginUsingId($originalUserId);
        $request->session()->forget('impersonation');
        $request->session()->regenerate();

        return redirect(DashboardRedirect::forUser(Auth::user()))
            ->with('success', 'Impersonation stopped.');
    }
}
