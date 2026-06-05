@extends('layouts.student')
@section('title', 'Placements')
@section('page-title', 'Placement & Career')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Placements</li>
@endsection

@section('content')

{{-- Banner --}}
<div class="card mb-4" style="background: linear-gradient(135deg, var(--clr-primary), #6366f1); color: #fff;">
    <div class="card-body py-4">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-briefcase-fill fs-1 opacity-75"></i>
            <div>
                <h4 class="mb-1 fw-bold">Placement & Career</h4>
                <div class="opacity-75">Browse open drives, apply, and track your application status</div>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Tabs --}}
<ul class="nav nav-tabs mb-4" id="placementTabs">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#openDrives">
            <i class="bi bi-briefcase me-1"></i>Open Drives
            <span class="badge bg-primary ms-1">{{ $drives->count() }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#myApplications">
            <i class="bi bi-person-check me-1"></i>My Applications
            <span class="badge bg-secondary ms-1">{{ $myApplications->count() }}</span>
        </a>
    </li>
</ul>

<div class="tab-content">
    {{-- Open Drives --}}
    <div class="tab-pane fade show active" id="openDrives">
        @if($drives->isEmpty())
            <div class="card">
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-briefcase fs-1 d-block mb-2"></i>
                    No open placement drives at the moment.
                </div>
            </div>
        @else
            <div class="row g-3">
                @foreach($drives as $drive)
                @php $alreadyApplied = in_array($drive->id, $myApplicationDriveIds); @endphp
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="fw-bold mb-0">{{ $drive->title }}</h6>
                                    <div class="text-muted" style="font-size:.82rem">{{ $drive->company->name }}</div>
                                </div>
                                @php
                                    $badge = $drive->status === 'ongoing' ? 'bg-warning text-dark' : 'bg-info';
                                @endphp
                                <span class="badge {{ $badge }}">{{ ucfirst($drive->status) }}</span>
                            </div>
                            <div class="mb-2">
                                <span class="badge bg-primary me-1">{{ $drive->job_role }}</span>
                                @if($drive->package)
                                    <span class="badge bg-success">{{ $drive->package }}</span>
                                @endif
                            </div>
                            <div class="text-muted" style="font-size:.8rem">
                                @if($drive->min_cgpa)
                                    <div><i class="bi bi-mortarboard me-1"></i>Min CGPA: {{ $drive->min_cgpa }}</div>
                                @endif
                                @if($drive->drive_date)
                                    <div><i class="bi bi-calendar me-1"></i>Drive Date: {{ $drive->drive_date->format('d M Y') }}</div>
                                @endif
                                @if($drive->last_apply_date)
                                    <div><i class="bi bi-clock me-1"></i>Apply by: {{ $drive->last_apply_date->format('d M Y') }}</div>
                                @endif
                                @if($drive->location)
                                    <div><i class="bi bi-geo-alt me-1"></i>{{ $drive->location }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            @if($alreadyApplied)
                                <button class="btn btn-sm btn-secondary w-100" disabled>
                                    <i class="bi bi-check-circle me-1"></i>Applied
                                </button>
                            @elseif(!$student)
                                <button class="btn btn-sm btn-outline-secondary w-100" disabled>
                                    Profile not set up
                                </button>
                            @else
                                <form method="POST" action="{{ route('student.placements.apply', $drive) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-primary w-100">
                                        <i class="bi bi-send me-1"></i>Apply Now
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- My Applications --}}
    <div class="tab-pane fade" id="myApplications">
        @if($myApplications->isEmpty())
            <div class="card">
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    You haven't applied to any drives yet.
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Drive / Company</th>
                                    <th>Job Role</th>
                                    <th>Package</th>
                                    <th>Status</th>
                                    <th>Applied On</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($myApplications as $app)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $app->drive->title }}</div>
                                        <div class="text-muted" style="font-size:.78rem">{{ $app->drive->company->name }}</div>
                                    </td>
                                    <td>{{ $app->drive->job_role }}</td>
                                    <td>{{ $app->drive->package ?? '—' }}</td>
                                    <td>
                                        @php
                                            $statusBadge = [
                                                'applied'     => 'bg-info',
                                                'shortlisted' => 'bg-primary',
                                                'interview'   => 'bg-warning text-dark',
                                                'selected'    => 'bg-success',
                                                'rejected'    => 'bg-danger',
                                                'withdrawn'   => 'bg-secondary',
                                            ];
                                        @endphp
                                        <span class="badge {{ $statusBadge[$app->application_status] ?? 'bg-secondary' }}">
                                            {{ ucfirst($app->application_status) }}
                                        </span>
                                    </td>
                                    <td>{{ $app->created_at->format('d M Y') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@endsection
