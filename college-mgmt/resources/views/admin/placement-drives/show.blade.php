@extends('layouts.admin')
@section('title', $placementDrive->title)
@section('page-title', 'Drive Details')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.placement-drives.index') }}">Drives</a></li>
    <li class="breadcrumb-item active">{{ $placementDrive->title }}</li>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Drive Info --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h5 class="fw-bold mb-1">{{ $placementDrive->title }}</h5>
                <div class="text-muted">{{ $placementDrive->company->name }}</div>
            </div>
            <div class="d-flex gap-2">
                @php
                    $badgeMap = ['upcoming'=>'bg-info','ongoing'=>'bg-warning text-dark','completed'=>'bg-success','cancelled'=>'bg-secondary'];
                @endphp
                <span class="badge {{ $badgeMap[$placementDrive->status] ?? 'bg-secondary' }} fs-6">{{ ucfirst($placementDrive->status) }}</span>
                <a href="{{ route('admin.placement-drives.edit', $placementDrive) }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
            </div>
        </div>
        <hr>
        <div class="row g-3">
            <div class="col-sm-3">
                <div class="text-muted" style="font-size:.75rem">Job Role</div>
                <div class="fw-semibold">{{ $placementDrive->job_role }}</div>
            </div>
            <div class="col-sm-3">
                <div class="text-muted" style="font-size:.75rem">Package</div>
                <div class="fw-semibold">{{ $placementDrive->package ?? 'Package not published' }}</div>
            </div>
            <div class="col-sm-3">
                <div class="text-muted" style="font-size:.75rem">Drive Date</div>
                <div class="fw-semibold">{{ $placementDrive->drive_date ? $placementDrive->drive_date->format('d M Y') : 'Drive date not scheduled' }}</div>
            </div>
            <div class="col-sm-3">
                <div class="text-muted" style="font-size:.75rem">Last Apply Date</div>
                <div class="fw-semibold">{{ $placementDrive->last_apply_date ? $placementDrive->last_apply_date->format('d M Y') : 'Application deadline not published' }}</div>
            </div>
            <div class="col-sm-3">
                <div class="text-muted" style="font-size:.75rem">Location</div>
                <div class="fw-semibold">{{ $placementDrive->location ?? 'Location not published' }}</div>
            </div>
            <div class="col-sm-3">
                <div class="text-muted" style="font-size:.75rem">Min CGPA</div>
                <div class="fw-semibold">{{ $placementDrive->min_cgpa ?? 'CGPA rule not set' }}</div>
            </div>
            <div class="col-sm-3">
                <div class="text-muted" style="font-size:.75rem">Vacancies</div>
                <div class="fw-semibold">{{ $placementDrive->vacancies ?? 'Vacancies not published' }}</div>
            </div>
            <div class="col-sm-3">
                <div class="text-muted" style="font-size:.75rem">Eligibility</div>
                <div class="fw-semibold">{{ $placementDrive->eligibility ?? 'Eligibility not published' }}</div>
            </div>
            @if($placementDrive->description)
            <div class="col-12">
                <div class="text-muted" style="font-size:.75rem">Description</div>
                <div>{{ $placementDrive->description }}</div>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Add Student to Drive --}}
<div class="card mb-4">
    <div class="card-header py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-person-plus me-2"></i>Add Student to Drive</h6>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.placement-drives.apply', $placementDrive) }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-6">
                <label class="form-label form-label-sm">Select Student</label>
                <select aria-label="Student" name="student_id" class="form-select form-select-sm" required>
                    <option value="">Select student</option>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}">
                            {{ $student->user->name ?? 'Student name missing' }}
                            ({{ $student->enrollment_number ?? 'Enrollment pending' }})
                            @if($student->course) - {{ $student->course->name }} @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Add</button>
            </div>
        </form>
    </div>
</div>

{{-- Applications Table --}}
<div class="card">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Applications ({{ $placementDrive->placements->count() }})</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Student</th>
                        <th scope="col">Enrollment</th>
                        <th scope="col">Course</th>
                        <th scope="col">Application Status</th>
                        <th scope="col">Offered Package</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($placementDrive->placements as $placement)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $placement->student->user->name ?? 'Student name missing' }}</div>
                        </td>
                        <td>{{ $placement->student->enrollment_number ?? 'Enrollment pending' }}</td>
                        <td>{{ $placement->student->course->name ?? 'Course not linked' }}</td>
                        <td>
                            @php
                                $statusBadge = [
                                    'applied'     => 'bg-info',
                                    'shortlisted' => 'badge-pending',
                                    'interview'   => 'bg-warning text-dark',
                                    'selected'    => 'badge-active',
                                    'rejected'    => 'badge-danger',
                                    'withdrawn'   => 'bg-secondary',
                                ];
                            @endphp
                            <span class="badge {{ $statusBadge[$placement->application_status] ?? 'bg-secondary' }}">
                                {{ ucfirst($placement->application_status) }}
                            </span>
                        </td>
                        <td>{{ $placement->offered_package ? 'Rs. '.$placement->offered_package.' LPA' : 'Offer package not recorded' }}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateModal{{ $placement->id }}">
                                <i class="bi bi-pencil"></i> Update application
                            </button>
                        </td>
                    </tr>

                    {{-- Update Modal --}}
                    <div class="modal fade" id="updateModal{{ $placement->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('admin.placements.update-status', $placement) }}">
                                    @csrf @method('PATCH')
                                    <div class="modal-header">
                                        <h6 class="modal-title">Update Application - {{ $placement->student->user->name ?? 'Student' }}</h6>
                                        <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Application Status</label>
                                            <select aria-label="Application Status" name="application_status" class="form-select" required>
                                                @foreach(['applied','shortlisted','interview','selected','rejected','withdrawn'] as $s)
                                                    <option value="{{ $s }}" @selected($placement->application_status === $s)>{{ ucfirst($s) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Offered Package (LPA)</label>
                                            <input aria-label="Offered Package" type="number" step="0.01" name="offered_package" class="form-control"
                                                value="{{ $placement->offered_package }}">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Joining Date</label>
                                            <input aria-label="Joining Date" type="date" name="joining_date" class="form-control"
                                                value="{{ $placement->joining_date ? $placement->joining_date->format('Y-m-d') : '' }}">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Remarks</label>
                                            <textarea aria-label="Remarks" name="remarks" class="form-control" rows="2">{{ $placement->remarks }}</textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button class="btn btn-primary">Update application</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No applications yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
