@extends('layouts.admin')
@section('title', 'Assign Role')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.users.roles.index') }}">Role Assignments</a></li>
                    <li class="breadcrumb-item active">Assign Role</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0">Assign Role to User</h4>
            <span class="text-muted small">Grant a role — optionally scoped to a program, with optional expiry</span>
        </div>
    </div>

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.users.roles.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-semibold">User <span class="text-danger">*</span></label>
                            <select name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                                <option value="">— Select user —</option>
                                @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }} &lt;{{ $user->email }}&gt;
                                </option>
                                @endforeach
                            </select>
                            @error('user_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                            <select name="role_id" class="form-select @error('role_id') is-invalid @enderror" required>
                                <option value="">— Select role —</option>
                                @foreach($roles as $role)
                                <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                    {{ ucwords(str_replace('_', ' ', $role->name)) }}
                                </option>
                                @endforeach
                            </select>
                            @error('role_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Program Scope <span class="text-muted small">(optional)</span></label>
                            <select name="program_id" class="form-select @error('program_id') is-invalid @enderror">
                                <option value="">Global — all programs</option>
                                @foreach($programs as $program)
                                <option value="{{ $program->id }}" {{ old('program_id') == $program->id ? 'selected' : '' }}>
                                    {{ $program->name }}
                                </option>
                                @endforeach
                            </select>
                            <div class="form-text">Scope this role to a specific program (e.g., Program Chair for BCA only).</div>
                            @error('program_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Active Until <span class="text-muted small">(optional)</span></label>
                            <input type="date" name="active_until"
                                   class="form-control @error('active_until') is-invalid @enderror"
                                   value="{{ old('active_until') }}"
                                   min="{{ now()->addDay()->toDateString() }}">
                            <div class="form-text">Leave blank for indefinite access. Role auto-expires on this date.</div>
                            @error('active_until')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-check me-1"></i>Assign Role
                            </button>
                            <a href="{{ route('admin.users.roles.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mt-3 p-3 bg-light rounded small text-muted">
                <i class="bi bi-info-circle me-1"></i>
                Assigning a role grants the user the permissions associated with that role.
                This is tracked in the <a href="{{ route('admin.audit.index') }}">Audit Log</a>.
                To view the role hierarchy and permission matrix, see
                <a href="{{ route('admin.roles.hierarchy') }}">Role Hierarchy</a>.
            </div>
        </div>
    </div>
</div>
@endsection
