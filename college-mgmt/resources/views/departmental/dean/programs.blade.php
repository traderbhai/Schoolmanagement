@extends('layouts.admin')
@section('title', 'Dean — Programs')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-mortarboard me-2 text-primary"></i>Programs</h4>
</div>

<div class="row g-3">
@forelse($programs as $prog)
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold">{{ $prog->name }} <span class="badge bg-secondary">{{ $prog->code }}</span></h6>
                <p class="text-muted small mb-2">{{ $prog->department->name ?? '—' }}</p>
                <div class="row text-center g-2">
                    <div class="col-4"><div class="fw-bold text-primary">{{ $prog->students_count }}</div><div class="small text-muted">Students</div></div>
                    <div class="col-4"><div class="fw-bold text-info">{{ $prog->batches_count }}</div><div class="small text-muted">Batches</div></div>
                    <div class="col-4"><div class="fw-bold text-success">{{ $prog->subjects_count }}</div><div class="small text-muted">Subjects</div></div>
                </div>
                <div class="mt-2 text-muted small">Faculty: {{ $prog->faculty_count }}</div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="{{ route('dean.students') }}?program_id={{ $prog->id }}" class="btn btn-sm btn-outline-primary w-100">View Students</a>
            </div>
        </div>
    </div>
@empty
    <div class="col-12"><p class="text-muted">No active programs.</p></div>
@endforelse
</div>
@endsection
