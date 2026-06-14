@extends('layouts.admin')
@section('title', 'Academics Governance')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Academics Governance</h1>
            <div class="text-muted small">Flexible hierarchy, branches, reporting lines, scopes, and permission matrix for Dean Office, PMC, CoE, IQAC, and Program Leadership.</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('department-hierarchy.index', ['department_id' => $department->id]) }}">
                <i class="bi bi-diagram-3"></i> Generic Hierarchy
            </a>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('department-governance.index', ['department_id' => $department->id]) }}">
                <i class="bi bi-sliders"></i> Feature Governance
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <a class="text-decoration-none" href="#branches">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="text-muted small">Branches</div>
                        <div class="h4 mb-0">{{ $branches->count() }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a class="text-decoration-none" href="#roles">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="text-muted small">Role Levels</div>
                        <div class="h4 mb-0">{{ $roles->count() }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a class="text-decoration-none" href="#members">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="text-muted small">Active Members</div>
                        <div class="h4 mb-0">{{ $members->count() }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a class="text-decoration-none" href="#scopes">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="text-muted small">Academic Scopes</div>
                        <div class="h4 mb-0">{{ $scopeAssignments->count() }}</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card shadow-sm" id="members">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span class="fw-semibold">Hierarchy Tree</span>
                    <span class="badge text-bg-light">{{ $department->code }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Branch</th>
                                <th>Role</th>
                                <th>Reports To</th>
                                <th>Visibility</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($members as $member)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $member->user?->name }}</div>
                                        <div class="small text-muted">{{ $member->user?->email }}</div>
                                    </td>
                                    <td>{{ $member->team?->name ?? 'Unassigned' }}</td>
                                    <td>
                                        <div>{{ $member->role?->name }}</div>
                                        <div class="small text-muted">Level {{ $member->role?->level }}</div>
                                    </td>
                                    <td>{{ $member->manager?->user?->name ?? 'Top level' }}</td>
                                    <td>
                                        @if($member->role?->can_view_team_data)
                                            <span class="badge text-bg-primary">Team</span>
                                        @else
                                            <span class="badge text-bg-secondary">Assigned</span>
                                        @endif
                                        @if(collect($member->role?->permissions ?? [])->contains('view_all'))
                                            <span class="badge text-bg-success">All</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No active academic members are configured.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card shadow-sm" id="branches">
                <div class="card-header fw-semibold py-2">Academic Branches</div>
                <div class="list-group list-group-flush">
                    @foreach($branches as $branch)
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>{{ $branch->name }}</span>
                            <span class="badge text-bg-light">{{ $branch->members->where('is_active', true)->count() }} members</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card shadow-sm mt-3">
                <div class="card-header fw-semibold py-2">Quick Setup Links</div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a class="btn btn-outline-primary btn-sm" href="{{ route('department-hierarchy.index', ['department_id' => $department->id]) }}">Create levels, branches, and members</a>
                        <a class="btn btn-outline-primary btn-sm" href="#scopes">Assign program, batch, term, and course scope</a>
                        <a class="btn btn-outline-primary btn-sm" href="#permissions">Review permission matrix</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-xl-7">
            <div class="card shadow-sm" id="scopes">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span class="fw-semibold">Academic Scope Assignments</span>
                    <span class="small text-muted">Program, batch, term, course, cohort, branch</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Scope</th>
                                <th>Context</th>
                                <th>Manage</th>
                                @if($canConfigure)<th></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($scopeAssignments as $scope)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $scope->user?->name }}</div>
                                        <div class="small text-muted">{{ $scope->member?->role?->name }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $scope->scope_name }}</div>
                                        <div class="small text-muted">{{ $scope->scope_type }} {{ $scope->scope_code ? '(' . $scope->scope_code . ')' : '' }}</div>
                                    </td>
                                    <td>{{ $scope->context ?? '-' }}</td>
                                    <td>{{ $scope->can_manage ? 'Yes' : 'No' }}</td>
                                    @if($canConfigure)
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('academics.scopes.deactivate', $scope) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-sm btn-outline-danger">Remove</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ $canConfigure ? 5 : 4 }}" class="text-center text-muted py-4">No academic scopes assigned yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold py-2">Assign Academic Scope</div>
                <div class="card-body">
                    @if($canConfigure)
                        <form method="POST" action="{{ route('academics.scopes.store') }}" class="row g-2">
                            @csrf
                            <div class="col-12">
                                <label class="form-label">Member</label>
                                <select name="department_member_id" class="form-select form-select-sm" required>
                                    @foreach($members as $member)
                                        <option value="{{ $member->id }}">{{ $member->user?->name }} - {{ $member->role?->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Scope Type</label>
                                <select name="scope_type" class="form-select form-select-sm" required>
                                    @foreach(\App\Services\AcademicScopeService::SCOPE_TYPES as $type)
                                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Scope ID</label>
                                <input name="scope_id" type="number" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Code</label>
                                <input name="scope_code" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Context</label>
                                <input name="context" class="form-control form-control-sm" value="academics">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Scope Name</label>
                                <input name="scope_name" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="can_manage" value="1" id="canManageScope">
                                    <label class="form-check-label" for="canManageScope">Can manage this scope</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary btn-sm w-100">Assign Scope</button>
                            </div>
                        </form>
                        <div class="small text-muted mt-3">Use IDs from the source tables. Current programs: {{ $programs->pluck('code')->join(', ') ?: 'none' }}.</div>
                    @else
                        <div class="text-muted">You can view Academics governance, but scope changes require Department Owner, Dean, or Admin privileges.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-xl-7">
            <div class="card shadow-sm" id="permissions">
                <div class="card-header fw-semibold py-2">Permission Matrix</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Level</th>
                                <th>Role</th>
                                <th>Capabilities</th>
                                <th>Flags</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roles as $role)
                                <tr>
                                    <td>{{ $role->level }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $role->name }}</div>
                                        <div class="small text-muted">{{ $role->code }}</div>
                                    </td>
                                    <td class="small">{{ implode(', ', $role->permissions ?? []) ?: '-' }}</td>
                                    <td>
                                        @if($role->can_manage_lower_levels)<span class="badge text-bg-primary">Manage lower</span>@endif
                                        @if($role->can_assign_work)<span class="badge text-bg-info">Assign</span>@endif
                                        @if($role->can_view_team_data)<span class="badge text-bg-success">Team data</span>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold py-2">Audit Trail</div>
                <div class="list-group list-group-flush">
                    @forelse($activityLogs as $log)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between gap-2">
                                <div class="fw-semibold small">{{ str_replace('_', ' ', ucfirst($log->action)) }}</div>
                                <div class="text-muted small">{{ $log->created_at?->diffForHumans() }}</div>
                            </div>
                            <div class="small">{{ $log->description }}</div>
                            <div class="small text-muted">{{ $log->actor?->name ?? 'System' }}{{ $log->target ? ' -> ' . $log->target->name : '' }}</div>
                        </div>
                    @empty
                        <div class="list-group-item text-muted">No Academics audit events yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
