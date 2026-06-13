@extends('layouts.admin')
@section('title', 'Department Hierarchy')
@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Department Hierarchy</h1>
            <div class="text-muted">Configure reusable roles, teams, and reporting lines for any department.</div>
        </div>
        <form method="GET" class="d-flex gap-2">
            <select name="department_id" class="form-select" onchange="this.form.submit()">
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" @selected($department?->id === $dept->id)>{{ $dept->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if(!$department)
        <div class="alert alert-info">Create an active department first.</div>
    @else
    <div class="row g-4">
        <div class="col-xl-4">
            <form method="POST" action="{{ route('department-hierarchy.roles.store') }}" class="card h-100">
                @csrf
                <input type="hidden" name="department_id" value="{{ $department->id }}">
                <div class="card-header fw-semibold">Add Role Level</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Role Name</label>
                        <input name="name" class="form-control" placeholder="Admission Director" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label">Code</label>
                            <input name="code" class="form-control" placeholder="admission_director">
                        </div>
                        <div class="col-5">
                            <label class="form-label">Level</label>
                            <input name="level" type="number" min="1" class="form-control" value="50" required>
                        </div>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="can_manage_lower_levels" value="1" id="manageLower">
                        <label class="form-check-label" for="manageLower">Can manage lower levels</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="can_view_team_data" value="1" id="viewTeam">
                        <label class="form-check-label" for="viewTeam">Can view team data</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="can_assign_work" value="1" id="assignWork">
                        <label class="form-check-label" for="assignWork">Can assign work</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Permissions</label>
                        <select name="permissions[]" class="form-select" multiple>
                            @foreach([
                                'manage_department_settings',
                                'configure_department',
                                'view_all',
                                'view_team',
                                'view_assigned',
                                'assign_work',
                                'operate',
                                'follow_up',
                                'verify_documents',
                                'verify_payments',
                                'approve_offers',
                                'configure_process',
                                'export_reports',
                            ] as $permission)
                                <option value="{{ $permission }}">{{ str_replace('_', ' ', ucfirst($permission)) }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Settings and hierarchy configuration permissions are reserved for owner/head-level roles.</div>
                    </div>
                    <button class="btn btn-primary w-100">Save Role</button>
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            <form method="POST" action="{{ route('department-hierarchy.teams.store') }}" class="card h-100">
                @csrf
                <input type="hidden" name="department_id" value="{{ $department->id }}">
                <div class="card-header fw-semibold">Add Team</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Team Name</label>
                        <input name="name" class="form-control" placeholder="North Region Team" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Team Type</label>
                        <select name="type" class="form-select" required>
                            @foreach(['custom', 'region', 'program', 'source', 'campus', 'function'] as $type)
                                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Parent Team</label>
                        <select name="parent_id" class="form-select">
                            <option value="">None</option>
                            @foreach($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-primary w-100">Save Team</button>
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            <form method="POST" action="{{ route('department-hierarchy.members.store') }}" class="card h-100">
                @csrf
                <input type="hidden" name="department_id" value="{{ $department->id }}">
                <div class="card-header fw-semibold">Add Member</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">User</label>
                        <select name="user_id" class="form-select" required>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} - {{ $user->email }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role Level</label>
                        <select name="department_role_id" class="form-select" required>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }} (L{{ $role->level }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Team</label>
                        <select name="department_team_id" class="form-select">
                            <option value="">No team</option>
                            @foreach($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reports To</label>
                        <select name="reports_to_member_id" class="form-select">
                            <option value="">Top-level</option>
                            @foreach($members as $member)
                                <option value="{{ $member->id }}">{{ $member->user?->name }} - {{ $member->role?->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-primary w-100">Save Member</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header fw-semibold">Configured Roles</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Level</th><th>Role</th><th>Members</th><th></th></tr></thead>
                        <tbody>
                            @forelse($roles as $role)
                                <tr>
                                    <td>{{ $role->level }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $role->name }}</div>
                                        <div class="small text-muted">{{ $role->code }}</div>
                                        <div class="small text-muted">{{ implode(', ', $role->permissions ?? []) ?: '-' }}</div>
                                    </td>
                                    <td>{{ $role->members_count }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('department-hierarchy.roles.deactivate', $role) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-danger">Deactivate</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No roles configured.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header fw-semibold">Configured Teams</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Team</th><th>Type</th><th>Parent</th><th></th></tr></thead>
                        <tbody>
                            @forelse($teams as $team)
                                <tr>
                                    <td>{{ $team->name }}</td>
                                    <td>{{ ucfirst($team->type) }}</td>
                                    <td>{{ $team->parent?->name ?? '-' }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('department-hierarchy.teams.deactivate', $team) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-danger">Deactivate</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No teams configured.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header fw-semibold">Reporting Structure</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>User</th><th>Role</th><th>Reports To</th><th></th></tr></thead>
                        <tbody>
                            @forelse($members as $member)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $member->user?->name }}</div>
                                        <div class="small text-muted">{{ $member->user?->email }}</div>
                                        <div class="small text-muted">{{ $member->team?->name ?? 'No team' }}</div>
                                    </td>
                                    <td>{{ $member->role?->name }}</td>
                                    <td>{{ $member->manager?->user?->name ?? 'Top-level' }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('department-hierarchy.members.deactivate', $member) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-danger">Deactivate</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No members configured.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
