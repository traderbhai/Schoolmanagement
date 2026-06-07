@extends('layouts.admin')
@section('title', 'Director Dashboard')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Institute Director</h4>
            <span class="text-muted small">Institution-wide overview — enrollment, academics, placements</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('director.reports') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-file-earmark-bar-graph me-1"></i> Reports
            </a>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-2 col-sm-4">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-body py-3">
                    <div class="fw-bold fs-3 text-primary">{{ $totalStudents }}</div>
                    <div class="text-muted small">Total Students</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-body py-3">
                    <div class="fw-bold fs-3 text-success">{{ $totalPrograms }}</div>
                    <div class="text-muted small">Active Programs</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-body py-3">
                    <div class="fw-bold fs-3 text-info">{{ $totalFaculty }}</div>
                    <div class="text-muted small">Faculty Members</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-body py-3">
                    <div class="fw-bold fs-3 text-warning">{{ $activeDrives }}</div>
                    <div class="text-muted small">Active Drives</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-4">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-body py-3">
                    <div class="fw-bold fs-3 text-success">{{ $placedThisYear }}</div>
                    <div class="text-muted small">Placed {{ now()->year }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Program Enrollment --}}
    <div class="row g-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between">
                    <h6 class="fw-semibold mb-0">Enrollment by Program</h6>
                    <a href="{{ route('director.programs') }}" class="btn btn-sm btn-outline-secondary">All Programs</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Program</th>
                                    <th>Code</th>
                                    <th class="text-end pe-3">Students</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($programs as $program)
                                <tr>
                                    <td class="ps-3 fw-medium">{{ $program->name }}</td>
                                    <td><span class="badge bg-primary-subtle text-primary">{{ $program->code ?? '—' }}</span></td>
                                    <td class="text-end pe-3">{{ $program->students_count }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">No programs.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="fw-semibold mb-0">Director Quick Links</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('dean.dashboard') }}" class="btn btn-outline-primary text-start btn-sm">
                            <i class="bi bi-mortarboard me-2"></i>Dean Academic View
                        </a>
                        <a href="{{ route('accounts.dashboard') }}" class="btn btn-outline-secondary text-start btn-sm">
                            <i class="bi bi-cash-stack me-2"></i>Accounts View
                        </a>
                        <a href="{{ route('cmc.dashboard') }}" class="btn btn-outline-success text-start btn-sm">
                            <i class="bi bi-briefcase me-2"></i>Placement / CMC View
                        </a>
                        <a href="{{ route('admin.audit.index') }}" class="btn btn-outline-dark text-start btn-sm">
                            <i class="bi bi-journal-text me-2"></i>Audit Log
                        </a>
                        <a href="{{ route('director.reports') }}" class="btn btn-outline-info text-start btn-sm">
                            <i class="bi bi-file-earmark-bar-graph me-2"></i>Institutional Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
