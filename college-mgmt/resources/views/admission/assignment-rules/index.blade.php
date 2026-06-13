@extends('layouts.admin')
@section('title', 'Assignment Rules')
@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">Assignment Rules</h1>
            <div class="text-muted">Route leads and applicants by program, source, region, priority, team, status, and workload.</div>
        </div>
        <a href="{{ route('admission.workbench') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-kanban me-1"></i>Workbench</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <form method="POST" action="{{ route('admission.assignment-rules.store') }}" class="card">
                @csrf
                <div class="card-header fw-semibold">Create Rule</div>
                <div class="card-body vstack gap-3">
                    <input name="name" class="form-control" placeholder="Rule name" required>
                    <div class="row g-2">
                        <div class="col"><select name="object_type" class="form-select"><option value="lead">Lead</option><option value="applicant">Applicant</option></select></div>
                        <div class="col"><input name="priority" type="number" class="form-control" value="100" min="1"></div>
                    </div>
                    <select name="assignee_strategy" class="form-select">
                        @foreach(['round_robin','least_workload','fixed_user','fixed_team','role_under_manager','keep_current_level'] as $strategy)
                            <option value="{{ $strategy }}">{{ ucwords(str_replace('_', ' ', $strategy)) }}</option>
                        @endforeach
                    </select>
                    <select name="target_user_id" class="form-select"><option value="">Target user</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>
                    <select name="target_team_id" class="form-select"><option value="">Target team</option>@foreach($teams as $team)<option value="{{ $team->id }}">{{ $team->name }}</option>@endforeach</select>
                    <select name="target_role_id" class="form-select"><option value="">Target role</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select>
                    <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Rule</button>
                </div>
            </form>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Priority</th><th>Name</th><th>Object</th><th>Strategy</th><th>Target</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @forelse($rules as $rule)
                            <tr>
                                <td>{{ $rule->priority }}</td>
                                <td class="fw-semibold">{{ $rule->name }}</td>
                                <td>{{ ucfirst($rule->object_type) }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $rule->assignee_strategy)) }}</td>
                                <td>{{ $rule->targetUser?->name ?? $rule->targetTeam?->name ?? $rule->targetRole?->name ?? '-' }}</td>
                                <td><span class="badge bg-{{ $rule->is_active ? 'success' : 'secondary' }}">{{ $rule->is_active ? 'Active' : 'Off' }}</span></td>
                                <td><form method="POST" action="{{ route('admission.assignment-rules.toggle', $rule) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">Toggle</button></form></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No rules configured.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">{{ $rules->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
