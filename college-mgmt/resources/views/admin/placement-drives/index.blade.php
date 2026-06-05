@extends('layouts.admin')
@section('title', 'Placement Drives')
@section('page-title', 'Placement Drives')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Placement Drives</li>
@endsection

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Placement Drives</h5>
        <div class="text-muted" style="font-size:.82rem">Manage job drives and student applications</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.placements.export') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
        <a href="{{ route('admin.placement-drives.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add Drive
        </a>
    </div>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 d-flex align-items-center justify-content-center bg-primary bg-opacity-10" style="width:46px;height:46px">
                    <i class="bi bi-briefcase text-primary fs-5"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size:.75rem">Total Drives</div>
                    <div class="fw-bold fs-4">{{ $totalDrives }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 d-flex align-items-center justify-content-center bg-info bg-opacity-10" style="width:46px;height:46px">
                    <i class="bi bi-calendar-event text-info fs-5"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size:.75rem">Upcoming</div>
                    <div class="fw-bold fs-4">{{ $upcomingDrives }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 d-flex align-items-center justify-content-center bg-warning bg-opacity-10" style="width:46px;height:46px">
                    <i class="bi bi-play-circle text-warning fs-5"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size:.75rem">Ongoing</div>
                    <div class="fw-bold fs-4">{{ $ongoingDrives }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 d-flex align-items-center justify-content-center bg-success bg-opacity-10" style="width:46px;height:46px">
                    <i class="bi bi-check-all text-success fs-5"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size:.75rem">Completed</div>
                    <div class="fw-bold fs-4">{{ $completedDrives }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Title, job role…" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(['upcoming','ongoing','completed','cancelled'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Company</label>
                <select name="company_id" class="form-select form-select-sm">
                    <option value="">All Companies</option>
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}" @selected(request('company_id') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Filter</button>
                @if(request()->hasAny(['search','status','company_id']))
                    <a href="{{ route('admin.placement-drives.index') }}" class="btn btn-sm btn-outline-secondary ms-1">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-body p-0">
        @if(session('success'))
            <div class="alert alert-success m-3">{{ session('success') }}</div>
        @endif
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Drive</th>
                        <th>Company</th>
                        <th>Job Role</th>
                        <th>Package</th>
                        <th>Drive Date</th>
                        <th>Applications</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($drives as $drive)
                    <tr>
                        <td>
                            <a href="{{ route('admin.placement-drives.show', $drive) }}" class="fw-semibold text-decoration-none">{{ $drive->title }}</a>
                        </td>
                        <td>{{ $drive->company->name }}</td>
                        <td>{{ $drive->job_role }}</td>
                        <td>{{ $drive->package ?? '—' }}</td>
                        <td>{{ $drive->drive_date ? $drive->drive_date->format('d M Y') : '—' }}</td>
                        <td><span class="badge bg-primary">{{ $drive->placements_count }}</span></td>
                        <td>
                            @php
                                $badgeMap = [
                                    'upcoming'  => 'bg-info',
                                    'ongoing'   => 'bg-warning text-dark',
                                    'completed' => 'bg-success',
                                    'cancelled' => 'bg-secondary',
                                ];
                            @endphp
                            <span class="badge {{ $badgeMap[$drive->status] ?? 'bg-secondary' }}">{{ ucfirst($drive->status) }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.placement-drives.show', $drive) }}" class="btn btn-sm btn-outline-info me-1" title="View"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('admin.placement-drives.edit', $drive) }}" class="btn btn-sm btn-outline-primary me-1" title="Edit"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('admin.placement-drives.destroy', $drive) }}" class="d-inline" onsubmit="return confirm('Delete this drive?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No placement drives found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($drives->hasPages())
    <div class="card-footer">
        {{ $drives->links() }}
    </div>
    @endif
</div>

@endsection
