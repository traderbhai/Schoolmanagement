@extends('layouts.student')
@section('title', 'Placements')
@section('page-title', 'Placement & Career')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Placements</li>
@endsection

@section('content')
<div class="card mb-4" style="background: linear-gradient(135deg, var(--clr-primary), #6366f1); color: #fff;">
    <div class="card-body py-4">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-briefcase-fill fs-1 opacity-75"></i>
            <div>
                <h4 class="mb-1 fw-bold">Placement & Career</h4>
                <div class="opacity-75">Browse drives, apply before deadlines, and track every application outcome.</div>
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

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">Placement Priority</div>
            <h5 class="fw-bold mb-1">{{ $placementPriority['title'] }}</h5>
            <p class="text-muted mb-0">{{ $placementPriority['body'] }}</p>
        </div>
        <a href="{{ $placementPriority['route'] }}" class="btn btn-sm {{ $placementPriority['level'] === 'danger' ? 'btn-danger' : ($placementPriority['level'] === 'warning' ? 'btn-warning' : 'btn-primary') }}">
            <i class="bi bi-arrow-right-circle me-1"></i>{{ $placementPriority['action'] }}
        </a>
    </div>
</div>

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
    <div class="tab-pane fade show active" id="openDrives">
        @if($drives->isEmpty())
            <div class="card">
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-briefcase fs-1 d-block mb-2"></i>
                    <div class="fw-semibold text-dark mb-1">No placement drives are open right now</div>
                    <div class="small mx-auto" style="max-width:560px">
                        CMC publishes drives after company details, eligibility, application deadline, and drive date are confirmed.
                        Check this page again for upcoming or ongoing drives, and use My Applications to track submitted applications.
                    </div>
                </div>
            </div>
        @else
            <div class="row g-3">
                @foreach($drives as $drive)
                @php
                    $alreadyApplied = in_array($drive->id, $myApplicationDriveIds);
                    $deadlinePassed = $drive->last_apply_date && $drive->last_apply_date->lt(now()->startOfDay());
                    $deadlineSoon = $drive->last_apply_date && ! $deadlinePassed && $drive->last_apply_date->lte(now()->addDays(3)->endOfDay());
                    $badge = $drive->status === 'ongoing' ? 'bg-warning text-dark' : 'bg-info';
                    $eligibility = $drive->student_eligibility ?? ['eligible' => true, 'reason' => null, 'detail' => null];
                @endphp
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                                <div>
                                    <h6 class="fw-bold mb-0">{{ $drive->title }}</h6>
                                    <div class="text-muted" style="font-size:.82rem">{{ $drive->company->name ?? 'Company pending' }}</div>
                                </div>
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
                                @if(!$eligibility['eligible'] && !empty($eligibility['detail']))
                                    <div class="text-danger fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>{{ $eligibility['detail'] }}</div>
                                @endif
                                @if($drive->drive_date)
                                    <div><i class="bi bi-calendar me-1"></i>Drive Date: {{ $drive->drive_date->format('d M Y') }}</div>
                                @endif
                                @if($drive->last_apply_date)
                                    <div class="{{ $deadlineSoon ? 'text-danger fw-semibold' : '' }}"><i class="bi bi-clock me-1"></i>Apply by: {{ $drive->last_apply_date->format('d M Y') }}</div>
                                @endif
                                @if($drive->location)
                                    <div><i class="bi bi-geo-alt me-1"></i>{{ $drive->location }}</div>
                                @endif
                                @if($drive->eligibility)
                                    <div class="mt-2">{{ $drive->eligibility }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-0 pt-0">
                            @if($alreadyApplied)
                                <button class="btn btn-sm btn-secondary w-100" disabled>
                                    <i class="bi bi-check-circle me-1"></i>Applied
                                </button>
                            @elseif(!$student)
                                <button class="btn btn-sm btn-outline-secondary w-100" disabled>
                                    Profile not set up
                                </button>
                            @elseif(!$canApplyForPlacements)
                                <button class="btn btn-sm btn-outline-secondary w-100" disabled>
                                    Active students only
                                </button>
                            @elseif($deadlinePassed)
                                <button class="btn btn-sm btn-outline-secondary w-100" disabled>
                                    Deadline passed
                                </button>
                            @elseif(!$eligibility['eligible'])
                                <button class="btn btn-sm btn-outline-danger w-100" disabled>
                                    {{ $eligibility['reason'] ?? 'Not eligible' }}
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

    <div class="tab-pane fade" id="myApplications">
        @include('student.partials.placement-applications-table', ['myApplications' => $myApplications])
    </div>
</div>
@endsection
