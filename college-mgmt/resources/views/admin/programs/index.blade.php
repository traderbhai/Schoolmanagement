@extends('layouts.admin')

@section('title', 'Programs — Admin')
@section('page-title', 'Programs')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Programs</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0 fw-bold">Academic Programs</h5>
        <small class="text-muted">Manage PGDM, MBA, BBA and other programs</small>
    </div>
    <a href="{{ route('admin.programs.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Add Program
    </a>
</div>

@if($programs->isEmpty())
    <div class="card text-center py-5">
        <div class="card-body">
            <i class="bi bi-mortarboard fs-1 text-muted mb-3 d-block"></i>
            <h6 class="text-muted">No programs found</h6>
            <p class="text-muted small mb-3">Start by adding your first academic program.</p>
            <a href="{{ route('admin.programs.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Add Program
            </a>
        </div>
    </div>
@else
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Department</th>
                        <th>Type</th>
                        <th>Duration</th>
                        <th>Terms</th>
                        <th>Batches</th>
                        <th>Students</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($programs as $program)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $program->name }}</div>
                            <small class="text-muted">{{ $program->code }}@if($program->abbreviation && $program->abbreviation !== $program->code) &middot; {{ $program->abbreviation }}@endif</small>
                        </td>
                        <td><small>{{ $program->department->name ?? '—' }}</small></td>
                        <td>
                            @php
                                $typeBadge = match($program->system_type) {
                                    'semester'  => 'bg-info text-white',
                                    'trimester' => 'bg-primary text-white',
                                    'annual'    => 'bg-success text-white',
                                    'quarter'   => 'bg-warning text-dark',
                                    default     => 'bg-secondary text-white',
                                };
                            @endphp
                            <span class="badge {{ $typeBadge }}">{{ ucfirst($program->system_type) }}</span>
                        </td>
                        <td>{{ $program->duration_years }} yr</td>
                        <td>{{ $program->total_terms }}</td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ $program->batches_count }}</span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ $program->students_count }}</span>
                        </td>
                        <td>
                            @if($program->is_active)
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.programs.show', $program) }}" class="btn btn-sm btn-outline-secondary me-1" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('admin.programs.edit', $program) }}" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#deleteModal"
                                data-action="{{ route('admin.programs.destroy', $program) }}"
                                data-name="{{ $program->name }}" title="Delete">
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
