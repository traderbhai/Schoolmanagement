@extends('layouts.admin')

@section('title', 'Internships & Industrial Training')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="mb-1">Internships &amp; Industrial Training</h2>
            <p class="text-muted mb-0">Manage student internships, industrial training, and live projects.</p>
        </div>
        <a href="{{ route('cmc.internships.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Register Internship
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">Internship Priority</div>
                <h5 class="fw-bold mb-1">{{ $internshipPriority['title'] }}</h5>
                <p class="text-muted mb-0">{{ $internshipPriority['body'] }}</p>
            </div>
            <a href="{{ $internshipPriority['route'] }}" class="btn btn-sm {{ $internshipPriority['level'] === 'danger' ? 'btn-danger' : ($internshipPriority['level'] === 'warning' ? 'btn-warning' : 'btn-primary') }}">
                <i class="bi bi-arrow-right-circle me-1"></i>{{ $internshipPriority['action'] }}
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-3 fw-bold text-info">{{ $ongoingCount }}</div>
                    <div class="text-muted small">Ongoing</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-3 fw-bold text-success">{{ $completedCount }}</div>
                    <div class="text-muted small">Completed</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-3 fw-bold text-danger">{{ $overdueCount }}</div>
                    <div class="text-muted small">Past planned end date</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 border-0 shadow-sm">
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

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Company</th>
                            <th>Role</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Dates</th>
                            <th>Stipend</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($internships as $internship)
                            @php
                                $typeLabels = ['internship' => 'Internship', 'industrial_training' => 'Industrial Training', 'live_project' => 'Live Project'];
                                $statusColors = ['ongoing' => 'info', 'completed' => 'success', 'dropped' => 'secondary'];
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $internship->student->user->name ?? '-' }}</div>
                                    <small class="text-muted">{{ $internship->student->enrollment_number ?? '' }}</small>
                                </td>
                                <td>{{ $internship->company_name }}</td>
                                <td>{{ $internship->role_title }}</td>
                                <td><span class="badge bg-light text-dark">{{ $typeLabels[$internship->type] ?? $internship->type }}</span></td>
                                <td><span class="badge bg-{{ $statusColors[$internship->status] ?? 'secondary' }}">{{ ucfirst($internship->status) }}</span></td>
                                <td class="small text-muted">
                                    {{ $internship->start_date->format('d M Y') }}
                                    @if($internship->end_date)
                                        to {{ $internship->end_date->format('d M Y') }}
                                    @endif
                                </td>
                                <td>{{ $internship->stipend ? 'Rs. ' . number_format($internship->stipend, 0) . '/mo' : '-' }}</td>
                                <td class="text-end">
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
