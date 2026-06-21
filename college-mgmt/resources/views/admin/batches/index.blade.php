@extends('layouts.admin')

@section('title', 'Batches — Admin')
@section('page-title', 'Batches')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Batches</li>
@endsection

@section('content')
@include('admin.partials.setup-sequence')

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0 fw-bold">Batches</h5>
        <small class="text-muted">Manage student cohorts per program</small>
    </div>
    <a href="{{ route('admin.batches.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Add Batch
    </a>
</div>

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <select name="program_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Programs</option>
                    @foreach(\App\Models\Program::orderBy('name')->get() as $prog)
                        <option value="{{ $prog->id }}" {{ request('program_id') == $prog->id ? 'selected' : '' }}>{{ $prog->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    @foreach(['upcoming','active','completed','cancelled'] as $s)
                        <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            @if(request()->hasAny(['program_id','status']))
                <div class="col-auto">
                    <a href="{{ route('admin.batches.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                </div>
            @endif
        </form>
    </div>
</div>

@php
    $filtered = $batches;
    if(request('program_id')) $filtered = $filtered->where('program_id', request('program_id'));
    if(request('status')) $filtered = $filtered->where('status', request('status'));
@endphp

@if($filtered->isEmpty())
    <div class="card text-center py-5">
        <div class="card-body">
            <i class="bi bi-collection fs-1 text-muted mb-3 d-block"></i>
            <h6 class="text-muted">No batches found</h6>
            <a href="{{ route('admin.batches.create') }}" class="btn btn-primary btn-sm mt-2">
                <i class="bi bi-plus-lg me-1"></i> Add Batch
            </a>
        </div>
    </div>
@else
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Program</th>
                        <th>Academic Year</th>
                        <th>Dates</th>
                        <th>Capacity</th>
                        <th>Students</th>
                        <th>Terms</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($filtered as $batch)
                    @php
                        $statusBadge = match($batch->status) {
                            'upcoming'  => 'bg-info text-white',
                            'active'    => 'bg-success text-white',
                            'completed' => 'bg-secondary text-white',
                            'cancelled' => 'bg-danger text-white',
                            default     => 'bg-secondary',
                        };
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $batch->name }}</div>
                            <small class="text-muted">{{ $batch->code }}</small>
                        </td>
                        <td><small>{{ $batch->program->name ?? '—' }}</small></td>
                        <td><small>{{ $batch->academicYear->name ?? '—' }}</small></td>
                        <td>
                            <small>{{ $batch->start_date?->format('M Y') }} — {{ $batch->end_date?->format('M Y') }}</small>
                        </td>
                        <td>{{ $batch->intake_capacity }}</td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ $batch->students_count }}</span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ $batch->terms_count }}</span>
                        </td>
                        <td><span class="badge {{ $statusBadge }}">{{ ucfirst($batch->status) }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('admin.batches.show', $batch) }}" class="btn btn-sm btn-outline-secondary me-1"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('admin.batches.edit', $batch) }}" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-pencil"></i></a>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#deleteModal"
                                data-action="{{ route('admin.batches.destroy', $batch) }}"
                                data-name="{{ $batch->name }}">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
