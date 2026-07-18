@extends('layouts.admin')
@section('title', 'Edit Permissions — ' . $role->name)

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.roles.permissions.index') }}">Role Permissions</a></li>
                    <li class="breadcrumb-item active">{{ $role->name }}</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0">{{ ucwords(str_replace('_', ' ', $role->name)) }}</h4>
            <span class="text-muted small">Assign Spatie permissions — optionally scoped to a program</span>
        </div>
        <a href="{{ route('admin.roles.hierarchy') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-diagram-3 me-1"></i> View Hierarchy
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.roles.permissions.update', $role) }}">
        @csrf
        @method('PUT')

        <div class="row g-4">
            {{-- Program Scope --}}
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <label class="form-label fw-semibold">Program Scope (optional)</label>
                        <select name="program_id" class="form-select w-auto" id="programSelect">
                            <option value="">Global (all programs)</option>
                            @foreach($programs as $program)
                            <option value="{{ $program->id }}">{{ $program->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">When a program is selected, these permissions apply only within that program context.</div>
                    </div>
                </div>
            </div>

            {{-- Permission checkboxes grouped by prefix --}}
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-0 pt-3 pb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="fw-semibold mb-0">Permissions</h6>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(true)">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">Clear All</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @php
                            $grouped = $permissions->groupBy(function($p) {
                                $parts = explode('.', $p->name);
                                return count($parts) > 1 ? $parts[0] : 'general';
                            });
                            $currentPermIds = $role->permissions->pluck('id')->toArray();
                        @endphp

                        <div class="row g-3">
                            @foreach($grouped as $group => $perms)
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="fw-semibold text-uppercase small text-muted mb-2" style="font-size:.7rem;letter-spacing:.07em">
                                        {{ $group }}
                                    </div>
                                    @foreach($perms as $perm)
                                    <div class="form-check mb-1">
                                        <input aria-label="Permissions" class="form-check-input perm-check" type="checkbox"
                                               name="permissions[]"
                                               value="{{ $perm->id }}"
                                               id="perm_{{ $perm->id }}"
                                               {{ in_array($perm->id, $currentPermIds) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="perm_{{ $perm->id }}">
                                            {{ $perm->name }}
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Program-specific overrides (existing) --}}
            @if($rolePermissions->count())
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-0 pt-3 pb-0">
                        <h6 class="fw-semibold mb-0">Existing Program-Specific Overrides</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th scope="col">Program</th>
                                        <th scope="col">Permissions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rolePermissions as $programId => $matrix)
                                    <tr>
                                        <td>
                                            @if($programId)
                                                {{ $matrix->first()->program->name ?? 'Unknown Program' }}
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary">Global</span>
                                            @endif
                                        </td>
                                        <td>
                                            @foreach($matrix as $m)
                                            <span class="badge bg-light text-secondary border me-1" style="font-size:.7rem">
                                                {{ $m->permission->name ?? $m->permission_id }}
                                            </span>
                                            @endforeach
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Save Permissions
                </button>
                <a href="{{ route('admin.roles.permissions.index') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>

<script>
function toggleAll(state) {
    document.querySelectorAll('.perm-check').forEach(cb => cb.checked = state);
}
</script>
@endsection
