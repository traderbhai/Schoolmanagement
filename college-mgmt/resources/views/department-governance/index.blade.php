@extends('layouts.admin')
@section('title', 'Department Governance')
@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Department Governance</h1>
            <div class="text-muted">Manage department features, visibility, activity, and controlled impersonation.</div>
        </div>
        <form method="GET" class="d-flex gap-2">
            <select aria-label="Department" name="department_id" class="form-select" onchange="this.form.submit()">
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" @selected($department->id === $dept->id)>{{ $dept->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            @if($canManageSettings)
            <form method="POST" action="{{ route('department-governance.features.update', $department) }}" class="card">
                @csrf
                <div class="card-header fw-semibold">Feature Toggle</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Feature</label>
                        <select aria-label="Feature Key" name="feature_key" class="form-select" required>
                            @foreach($features as $feature)
                                <option value="{{ $feature->feature_key }}">{{ $feature->feature_name }} - {{ $feature->feature_key }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="featureEnabled" checked>
                        <label class="form-check-label" for="featureEnabled">Enabled</label>
                    </div>
                    <button class="btn btn-primary w-100">Save Feature Setting</button>
                </div>
            </form>
            @else
                <div class="card">
                    <div class="card-header fw-semibold">Feature Toggle</div>
                    <div class="card-body text-muted">
                        Department feature settings are controlled by Admin or the department Head/Owner.
                    </div>
                </div>
            @endif

            <div class="card mt-4">
                <div class="card-header fw-semibold">Registered Features</div>
                <div class="list-group list-group-flush">
                    @forelse($features as $feature)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-semibold">{{ $feature->feature_name }}</div>
                                    <div class="small text-muted">{{ $feature->feature_key }}</div>
                                    @if($feature->description)
                                        <div class="small text-muted mt-1">{{ $feature->description }}</div>
                                    @endif
                                    <div class="small mt-1">
                                        <span class="badge bg-light text-dark">{{ $feature->category }}</span>
                                        @if($feature->has_override)
                                            <span class="badge bg-info text-dark">Configured</span>
                                        @else
                                            <span class="badge bg-secondary">Default</span>
                                        @endif
                                    </div>
                                </div>
                                @if($canManageSettings)
                                    <form method="POST" action="{{ route('department-governance.features.update', $department) }}">
                                        @csrf
                                        <input type="hidden" name="feature_key" value="{{ $feature->feature_key }}">
                                        <input type="hidden" name="feature_name" value="{{ $feature->feature_name }}">
                                        @if(!$feature->is_enabled)
                                            <input type="hidden" name="is_enabled" value="1">
                                        @endif
                                        <button class="btn btn-sm btn-outline-{{ $feature->is_enabled ? 'secondary' : 'success' }}">
                                            {{ $feature->is_enabled ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>
                                @else
                                    <span class="badge bg-{{ $feature->is_enabled ? 'success' : 'secondary' }}">
                                        {{ $feature->is_enabled ? 'Enabled' : 'Disabled' }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-muted">No registered features for this department yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header fw-semibold">Impersonate Subordinate</div>
                <div class="list-group list-group-flush">
                    @forelse($impersonatableMembers as $member)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-semibold">{{ $member->user?->name }}</div>
                                    <div class="small text-muted">{{ $member->role?->name }} {{ $member->team ? '- ' . $member->team->name : '' }}</div>
                                </div>
                                <form method="POST" action="{{ route('department-governance.impersonation.start', $member) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary">Login As</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-muted">No subordinate users available for impersonation.</div>
                    @endforelse
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header fw-semibold">Members</div>
                <div class="list-group list-group-flush">
                    @forelse($members as $member)
                        <div class="list-group-item">
                            <div class="fw-semibold">{{ $member->user?->name }}</div>
                            <div class="small text-muted">{{ $member->role?->name }} reports to {{ $member->manager?->user?->name ?? 'Top-level' }}</div>
                        </div>
                    @empty
                        <div class="list-group-item text-muted">No members configured.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header fw-semibold">Recent Department Activity</div>
                <div class="list-group list-group-flush">
                    @forelse($activityLogs as $log)
                        <div class="list-group-item">
                            <div class="fw-semibold">{{ $log->description }}</div>
                            <div class="small text-muted">
                                {{ $log->actor?->name ?? 'System' }} - {{ $log->created_at->diffForHumans() }}
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-muted">No department activity yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
