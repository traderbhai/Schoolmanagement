@extends('layouts.admin')
@section('title', 'Programs — Director View')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">All Programs</h4>
            <span class="text-muted small">Institution-level program enrollment overview</span>
        </div>
        <a href="{{ route('director.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <div class="row g-3">
        @forelse($programs as $program)
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="badge bg-primary-subtle text-primary px-2 mb-1">{{ $program->code ?? '—' }}</span>
                            <div class="fw-semibold">{{ $program->name }}</div>
                        </div>
                        <span class="badge bg-{{ $program->is_active ? 'success' : 'secondary' }}-subtle text-{{ $program->is_active ? 'success' : 'secondary' }}">
                            {{ $program->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="d-flex gap-3 text-muted small mt-3">
                        <span><i class="bi bi-people me-1"></i>{{ $program->students_count }} students</span>
                        @if($program->duration_years ?? false)
                        <span><i class="bi bi-calendar me-1"></i>{{ $program->duration_years }} years</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center text-muted py-4">No programs found.</div>
        @endforelse
    </div>
</div>
@endsection
