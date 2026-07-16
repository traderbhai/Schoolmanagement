@extends('layouts.admin')
@section('title', 'Director Dashboard')
@section('page-title', "Director's Dashboard")

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-bank me-2 text-primary"></i>Director's Dashboard</h4>
        <p class="text-muted mb-0 small">Institute-wide overview &mdash; {{ now()->format('l, d F Y') }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('director.reports') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-file-earmark-bar-graph me-1"></i>Full Reports
        </a>
        <a href="{{ route('admin.org-hierarchy.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-diagram-3 me-1"></i>Org Hierarchy
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">Director Priority</div>
            <h5 class="fw-bold mb-1">{{ $directorPriority['title'] }}</h5>
            <p class="text-muted mb-0">{{ $directorPriority['body'] }}</p>
        </div>
        <a href="{{ $directorPriority['route'] }}" class="btn btn-sm {{ $directorPriority['level'] === 'danger' ? 'btn-danger' : ($directorPriority['level'] === 'warning' ? 'btn-warning' : 'btn-primary') }}">
            <i class="bi bi-arrow-right-circle me-1"></i>{{ $directorPriority['action'] }}
        </a>
    </div>
</div>

{{-- KPI Row --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-4 col-xl-2">
        <x-ui.kpi-card tone="blue" icon="bi-people-fill" :value="$totalStudents" label="Total Students" trend="All programs" trend-icon="bi-mortarboard" />
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <x-ui.kpi-card tone="cyan" icon="bi-person-badge-fill" :value="$totalFaculty" label="Faculty" trend="Active staff" trend-icon="bi-building" />
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <x-ui.kpi-card tone="green" icon="bi-mortarboard-fill" :value="$totalPrograms" label="Programs" trend="Active programs" trend-icon="bi-journal" />
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <x-ui.kpi-card tone="purple" icon="bi-briefcase-fill" :value="$placedThisYear" label="Placed {{ now()->year }}" trend="Placement count" trend-icon="bi-arrow-up" trend-tone="up" />
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <x-ui.kpi-card tone="amber" icon="bi-clipboard-check-fill" :value="$activeDrives" label="Active Drives" trend="Open to students" trend-icon="bi-buildings" />
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <x-ui.kpi-card :tone="$overallAttendance >= 75 ? 'green' : 'red'" icon="bi-check2-square" value="{{ $overallAttendance }}%" label="Attendance" trend="Institute-wide" :trend-icon="$overallAttendance >= 75 ? 'bi-arrow-up' : 'bi-arrow-down'" :trend-tone="$overallAttendance >= 75 ? 'up' : 'down'" />
    </div>
</div>

{{-- Portal Summaries (from Org Hierarchy) --}}
@if(!empty($portalSummaries))
<div class="mb-4">
    <h6 class="fw-semibold text-muted text-uppercase mb-3" style="font-size:.72rem;letter-spacing:.08em">
        <i class="bi bi-grid me-1"></i>Department Portal Overviews
        <a href="{{ route('admin.org-hierarchy.index') }}" class="text-muted ms-2 small fw-normal">Configure &rsaquo;</a>
    </h6>
    <div class="row g-3">
        @foreach($portalSummaries as $portal)
        @php
            $colorMap = ['primary'=>'primary','success'=>'success','info'=>'info','warning'=>'warning','purple'=>'purple','dark'=>'dark'];
            $clr = $colorMap[$portal['color']] ?? 'secondary';
        @endphp
        <div class="col-sm-6 col-md-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent d-flex align-items-center gap-2 py-2">
                    <span class="rounded-circle d-flex align-items-center justify-content-center text-white"
                        style="width:30px;height:30px;background:var(--bs-{{ $clr }},var(--clr-primary));font-size:.9rem;flex-shrink:0">
                        <i class="bi {{ $portal['icon'] }}"></i>
                    </span>
                    <span class="fw-semibold small">{{ $portal['label'] }}</span>
                </div>
                <div class="card-body py-2 px-3">
                    @foreach($portal['metrics'] as $m)
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="border-color:var(--clr-border,#e5e7eb)!important">
                        <span class="text-muted" style="font-size:.78rem">{{ $m['label'] }}</span>
                        <span class="fw-bold small">{{ $m['value'] }}</span>
                    </div>
                    @endforeach
                </div>
                <div class="card-footer bg-transparent py-2 text-end">
                    <a href="{{ route($portal['route']) }}" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:.75rem">
                        Open Portal <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Program Enrollment --}}
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-mortarboard me-2"></i>Program Enrollment</span>
                <a href="{{ route('director.programs') }}" class="btn btn-sm btn-outline-primary">All Programs</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light"><tr><th>Program</th><th>Code</th><th class="text-end">Students</th></tr></thead>
                    <tbody>
                        @forelse($programs as $prog)
                        <tr>
                            <td class="fw-semibold">{{ $prog->name }}</td>
                            <td class="font-monospace text-muted">{{ $prog->code }}</td>
                            <td class="text-end">
                                <span class="badge bg-primary rounded-pill">{{ $prog->students_count }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">No programs found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Quick Access</div>
            <div class="card-body d-flex flex-column gap-2 p-3">
                <a href="{{ route('admin.analytics') }}" class="btn btn-sm btn-outline-primary text-start">
                    <i class="bi bi-graph-up-arrow me-2"></i>Institute Analytics
                </a>
                <a href="{{ route('admin.institutional-kpi') }}" class="btn btn-sm btn-outline-info text-start">
                    <i class="bi bi-speedometer2 me-2"></i>Institutional KPI
                </a>
                <a href="{{ route('admin.aicte-report') }}" class="btn btn-sm btn-outline-secondary text-start">
                    <i class="bi bi-file-earmark-bar-graph me-2"></i>AICTE Report
                </a>
                <a href="{{ route('cmc.placement-stats') }}" class="btn btn-sm btn-outline-success text-start">
                    <i class="bi bi-briefcase me-2"></i>Placement Statistics
                </a>
                <a href="{{ route('admin.org-hierarchy.index') }}" class="btn btn-sm btn-outline-dark text-start">
                    <i class="bi bi-diagram-3 me-2"></i>Configure Org Hierarchy
                </a>
            </div>
        </div>
    </div>
</div>

@endsection
