@extends('layouts.admin')
@section('title', 'CMC Dashboard')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Placement / CMC</h4>
            <span class="text-muted small">Placement drives, internships, career services</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.placement-drives.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Drive
            </a>
            <a href="{{ route('cmc.analytics') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-bar-chart me-1"></i> Analytics
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center" style="width:48px;height:48px">
                            <i class="bi bi-briefcase-fill text-primary fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-4">{{ $activeDrives }}</div>
                            <div class="text-muted small">Active Drives</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-success-subtle d-flex align-items-center justify-content-center" style="width:48px;height:48px">
                            <i class="bi bi-trophy-fill text-success fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-4">{{ $totalPlacements }}</div>
                            <div class="text-muted small">Total Placed</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-info-subtle d-flex align-items-center justify-content-center" style="width:48px;height:48px">
                            <i class="bi bi-people-fill text-info fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-4">{{ $totalStudents }}</div>
                            <div class="text-muted small">Total Students</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-warning-subtle d-flex align-items-center justify-content-center" style="width:48px;height:48px">
                            <i class="bi bi-percent text-warning fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-4">
                                {{ $totalStudents > 0 ? round(($totalPlacements / $totalStudents) * 100, 0) : 0 }}%
                            </div>
                            <div class="text-muted small">Placement Rate</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Drives --}}
    <div class="row g-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between">
                    <h6 class="fw-semibold mb-0">Recent Placement Drives</h6>
                    <a href="{{ route('cmc.drives') }}" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Company</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentDrives as $drive)
                                <tr>
                                    <td class="ps-3 fw-medium">{{ $drive->company->name ?? $drive->title ?? '—' }}</td>
                                    <td class="small text-muted">{{ ucfirst($drive->type ?? 'placement') }}</td>
                                    <td>
                                        @php
                                            $sc = ['open'=>'success','active'=>'primary','closed'=>'secondary','completed'=>'info'];
                                            $s = $drive->status ?? 'open';
                                        @endphp
                                        <span class="badge bg-{{ $sc[$s] ?? 'secondary' }}-subtle text-{{ $sc[$s] ?? 'secondary' }}">{{ $s }}</span>
                                    </td>
                                    <td class="small text-muted">{{ $drive->created_at->format('d M Y') }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">No drives yet.</td></tr>
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
                    <h6 class="fw-semibold mb-0">Quick Links</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('admin.placement-drives.index') }}" class="btn btn-outline-primary text-start">
                            <i class="bi bi-briefcase me-2"></i>All Placement Drives
                        </a>
                        <a href="{{ route('cmc.placements') }}" class="btn btn-outline-success text-start">
                            <i class="bi bi-trophy me-2"></i>Placed Students
                        </a>
                        <a href="{{ route('cmc.analytics') }}" class="btn btn-outline-secondary text-start">
                            <i class="bi bi-bar-chart me-2"></i>Placement Analytics
                        </a>
                        <a href="{{ route('admin.placements.export') }}" class="btn btn-outline-dark text-start">
                            <i class="bi bi-download me-2"></i>Export Placements
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
