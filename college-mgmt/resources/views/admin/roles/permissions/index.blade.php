@extends('layouts.admin')
@section('title', 'Role Permissions')

@section('content')
<div class="container-fluid py-4">
    @include('admin.partials.setup-sequence')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Role Permissions</h4>
            <span class="text-muted small">Manage Spatie permissions assigned to each role</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.roles.hierarchy') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-diagram-3 me-1"></i> Hierarchy
            </a>
            <a href="{{ route('admin.roles.feature-access.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-grid-3x3 me-1"></i> Feature Matrix
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row g-3">
        @foreach($roles as $role)
        @php
            $count = $role->permissions->count();
            $colorMap = [
                'admin'           => 'danger',
                'dean_academics'  => 'purple',
                'program_chair'   => 'primary',
                'hod'             => 'info',
                'exam_cell'       => 'warning',
                'faculty'         => 'success',
                'accounts_officer'=> 'secondary',
                'cmc'             => 'dark',
                'student'         => 'light',
            ];
            $color = $colorMap[$role->name] ?? 'primary';
        @endphp
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="badge bg-{{ $color }}-subtle text-{{ $color }} rounded-pill px-3 py-1 mb-1">
                                {{ $role->name }}
                            </span>
                            <div class="fw-semibold mt-1">{{ ucwords(str_replace('_', ' ', $role->name)) }}</div>
                        </div>
                        <a href="{{ route('admin.roles.permissions.show', $role) }}"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil-square me-1"></i>Edit
                        </a>
                    </div>

                    <div class="d-flex align-items-center gap-3 text-muted small">
                        <span><i class="bi bi-key me-1"></i>{{ $count }} permission{{ $count !== 1 ? 's' : '' }}</span>
                        @if(isset($programs) && $programs->count())
                        <span><i class="bi bi-diagram-2 me-1"></i>{{ $programs->count() }} programs</span>
                        @endif
                    </div>

                    @if($role->permissions->count())
                    <div class="mt-3">
                        @foreach($role->permissions->take(5) as $perm)
                        <span class="badge bg-light text-secondary border me-1 mb-1" style="font-size:.7rem">
                            {{ $perm->name }}
                        </span>
                        @endforeach
                        @if($role->permissions->count() > 5)
                        <span class="text-muted" style="font-size:.75rem">
                            +{{ $role->permissions->count() - 5 }} more
                        </span>
                        @endif
                    </div>
                    @else
                    <div class="mt-3 text-muted small fst-italic">No permissions assigned</div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-4 p-3 bg-light rounded small text-muted">
        <i class="bi bi-info-circle me-1"></i>
        These are role-level grants for core permissions. For fine-grained feature access (view / create / edit / approve), use the
        <a href="{{ route('admin.roles.feature-access.index') }}">Feature Access Matrix</a>.
    </div>
</div>
@endsection
