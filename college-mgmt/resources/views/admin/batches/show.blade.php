@extends('layouts.admin')

@section('title', $batch->name . ' — Admin')
@section('page-title', $batch->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.batches.index') }}">Batches</a></li>
    <li class="breadcrumb-item active">{{ $batch->code }}</li>
@endsection

@section('content')

@php
    $statusBadge = match($batch->status) {
        'upcoming'  => 'bg-info text-white',
        'active'    => 'bg-success text-white',
        'completed' => 'bg-secondary text-white',
        'cancelled' => 'bg-danger text-white',
        default     => 'bg-secondary',
    };
@endphp

{{-- Batch info header --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h5 class="fw-bold mb-1">{{ $batch->name }}</h5>
                <div class="d-flex gap-2 flex-wrap mb-2">
                    <span class="badge bg-secondary">{{ $batch->code }}</span>
                    <span class="badge {{ $statusBadge }}">{{ ucfirst($batch->status) }}</span>
                    <span class="badge bg-light text-dark border">{{ $batch->program->name ?? '—' }}</span>
                    @if($batch->academicYear)
                        <span class="badge bg-light text-dark border">{{ $batch->academicYear->name }}</span>
                    @endif
                </div>
                <small class="text-muted">
                    <i class="bi bi-calendar me-1"></i>
                    {{ $batch->start_date?->format('d M Y') }} — {{ $batch->end_date?->format('d M Y') }}
                </small>
            </div>
            <a href="{{ route('admin.batches.edit', $batch) }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
        </div>
        <hr class="my-2">
        <div class="row g-3 text-center">
            <div class="col-3">
                <div class="fw-bold fs-5">{{ $batch->intake_capacity }}</div>
                <small class="text-muted">Capacity</small>
            </div>
            <div class="col-3">
                <div class="fw-bold fs-5">{{ $batch->students->count() }}</div>
                <small class="text-muted">Enrolled</small>
            </div>
            <div class="col-3">
                <div class="fw-bold fs-5">{{ $batch->terms->count() }}</div>
                <small class="text-muted">Terms</small>
            </div>
            <div class="col-3">
                <div class="fw-bold fs-5">{{ $batch->terms->where('is_current', true)->count() ? $batch->terms->where('is_current', true)->first()->name : '—' }}</div>
                <small class="text-muted">Current Term</small>
            </div>
        </div>
    </div>
</div>

{{-- Terms management --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0 fw-bold">Terms / {{ $batch->program ? ucfirst($batch->program->system_type) . 's' : 'Terms' }}</h6>
    </div>
    <div class="card-body">
        @if($batch->terms->isNotEmpty())
        <div class="table-responsive mb-3">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Current</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($batch->terms as $term)
                    <tr @if($term->is_current) class="table-success" @endif>
                        <td>{{ $term->term_number }}</td>
                        <td class="fw-semibold">{{ $term->name }}</td>
                        <td><small>{{ $term->start_date?->format('d M Y') ?? '—' }}</small></td>
                        <td><small>{{ $term->end_date?->format('d M Y') ?? '—' }}</small></td>
                        <td>
                            @if($term->is_current)
                                <span class="badge bg-success">Current</span>
                            @else
                                <form method="POST" action="{{ route('admin.terms.set-current', $term) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn-xs btn-outline-secondary" style="font-size:.7rem;padding:2px 8px;">Set Current</button>
                                </form>
                            @endif
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-xs btn-outline-danger"
                                style="font-size:.7rem;padding:2px 8px;"
                                data-bs-toggle="modal" data-bs-target="#deleteModal"
                                data-action="{{ route('admin.terms.destroy', $term) }}"
                                data-name="{{ $term->name }}">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
            <p class="text-muted small mb-3">No terms added yet.</p>
        @endif

        {{-- Add term form --}}
        <form method="POST" action="{{ route('admin.terms.store') }}" class="row g-2 align-items-end border-top pt-3">
            @csrf
            <input type="hidden" name="batch_id" value="{{ $batch->id }}">
            <div class="col-md-1">
                <label class="form-label form-label-sm">#</label>
                <input type="number" name="term_number" class="form-control form-control-sm"
                    value="{{ $batch->terms->count() + 1 }}" min="1" required>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm">Name</label>
                <input type="text" name="name" class="form-control form-control-sm"
                    placeholder="Semester I" required>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">Start Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">End Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-plus-lg me-1"></i> Add Term
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Students in this batch --}}
<div class="card">
    <div class="card-header">
        <h6 class="mb-0 fw-bold">Students ({{ $batch->students->count() }})</h6>
    </div>
    @if($batch->students->isEmpty())
        <div class="card-body text-center py-4">
            <p class="text-muted small mb-0">No students assigned to this batch yet.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Enrollment</th>
                        <th>Specialization</th>
                        <th>Current Term</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($batch->students as $student)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $student->user->name ?? '—' }}</div>
                            <small class="text-muted">{{ $student->roll_number }}</small>
                        </td>
                        <td><code>{{ $student->enrollment_number }}</code></td>
                        <td>{{ $student->specialization->name ?? '—' }}</td>
                        <td>{{ $student->current_term ?? $student->current_semester }}</td>
                        <td>
                            @php
                                $sBadge = match($student->status) {
                                    'active' => 'bg-success', 'graduated' => 'bg-info',
                                    'dropped' => 'bg-danger', default => 'bg-secondary',
                                };
                            @endphp
                            <span class="badge {{ $sBadge }} text-white">{{ ucfirst($student->status) }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
