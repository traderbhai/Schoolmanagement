@extends('layouts.admin')

@section('title', 'Internships & Industrial Training')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Internships &amp; Industrial Training</h2>
            <p class="text-muted mb-0">Manage student internships, industrial training, and live projects</p>
        </div>
        <a href="{{ route('cmc.internships.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Register Internship
        </a>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="ongoing" @selected(request('status') === 'ongoing')>Ongoing</option>
                        <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                        <option value="dropped" @selected(request('status') === 'dropped')>Dropped</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="internship" @selected(request('type') === 'internship')>Internship</option>
                        <option value="industrial_training" @selected(request('type') === 'industrial_training')>Industrial Training</option>
                        <option value="live_project" @selected(request('type') === 'live_project')>Live Project</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary">Filter</button>
                    <a href="{{ route('cmc.internships.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Company</th>
                            <th>Role</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Start Date</th>
                            <th>Duration</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($internships as $internship)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $internship->student->user->name ?? '—' }}</div>
                                    <small class="text-muted">{{ $internship->student->enrollment_number ?? '' }}</small>
                                </td>
                                <td>{{ $internship->company_name }}</td>
                                <td>{{ $internship->role_title }}</td>
                                <td>
                                    @php
                                        $typeLabels = ['internship' => 'Internship', 'industrial_training' => 'Industrial Training', 'live_project' => 'Live Project'];
                                    @endphp
                                    <span class="badge bg-light text-dark">{{ $typeLabels[$internship->type] ?? $internship->type }}</span>
                                </td>
                                <td>
                                    @if ($internship->status === 'ongoing')
                                        <span class="badge bg-info">Ongoing</span>
                                    @elseif ($internship->status === 'completed')
                                        <span class="badge bg-success">Completed</span>
                                    @else
                                        <span class="badge bg-secondary">Dropped</span>
                                    @endif
                                </td>
                                <td>{{ $internship->start_date->format('d M Y') }}</td>
                                <td>
                                    @if ($internship->durationDays() !== null)
                                        {{ $internship->durationDays() }} days
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('cmc.internships.show', $internship) }}" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No internships found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($internships->hasPages())
            <div class="card-footer">{{ $internships->links() }}</div>
        @endif
    </div>
</div>
@endsection
