@extends('layouts.admin')
@section('title', 'Placement Statistics')

@section('content')
<div class="container-fluid py-4">
    <div class="mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-briefcase me-2 text-primary"></i>Placement Statistics</h4>
        <span class="text-muted small">Institution-wide placement performance overview</span>
    </div>

    {{-- KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-2">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-primary">{{ $totalStudents }}</div>
                    <div class="text-muted small">Active Students</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-success">{{ $placed }}</div>
                    <div class="text-muted small">Placed</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-info">{{ $placementRate }}%</div>
                    <div class="text-muted small">Placement Rate</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-warning">
                        {{ $avgSalary ? '₹'.number_format($avgSalary/100000,1).'L' : '—' }}
                    </div>
                    <div class="text-muted small">Avg Package</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-danger">{{ $activedrives }}</div>
                    <div class="text-muted small">Active Drives</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-secondary">{{ $alumniCount }}</div>
                    <div class="text-muted small">Alumni Verified</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Program-wise placement --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h6 class="fw-semibold text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.08em">PROGRAM-WISE PLACEMENT</h6>
                </div>
                <div class="card-body">
                    @forelse($programStats as $prog)
                    @php
                        $pct = $prog->placement_pct;
                        $color = $pct >= 80 ? 'success' : ($pct >= 50 ? 'warning' : 'danger');
                    @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold small">{{ $prog->name }}</span>
                            <span class="small text-muted">{{ $prog->placed_count }}/{{ $prog->student_count }} ({{ $pct }}%)</span>
                        </div>
                        <div class="progress" style="height:8px;">
                            <div class="progress-bar bg-{{ $color }}" style="width:{{ $pct }}%"></div>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted mb-0">No program data available.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Top Recruiters + Internship stats --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h6 class="fw-semibold text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.08em">TOP RECRUITERS</h6>
                </div>
                <div class="card-body">
                    @forelse($topRecruiters as $r)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="small fw-semibold">{{ $r['company']->name ?? 'Unknown' }}</span>
                        <span class="badge bg-primary rounded-pill">{{ $r['count'] }} placed</span>
                    </div>
                    @empty
                    <p class="text-muted small mb-0">No placement data yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h6 class="fw-semibold text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.08em">INTERNSHIPS</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-around text-center">
                        <div>
                            <div class="fs-3 fw-bold text-info">{{ $ongoingInternships }}</div>
                            <div class="text-muted small">Ongoing</div>
                        </div>
                        <div>
                            <div class="fs-3 fw-bold text-success">{{ $completedInternships }}</div>
                            <div class="text-muted small">Completed</div>
                        </div>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="{{ route('cmc.internships.index') }}" class="btn btn-outline-primary btn-sm">View All Internships</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
