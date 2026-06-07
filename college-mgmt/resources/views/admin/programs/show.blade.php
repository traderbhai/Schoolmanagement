@extends('layouts.admin')

@section('title', $program->name . ' — Admin')
@section('page-title', $program->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.programs.index') }}">Programs</a></li>
    <li class="breadcrumb-item active">{{ $program->code }}</li>
@endsection

@section('content')

{{-- Program info card --}}
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="fw-bold mb-1">{{ $program->name }}</h5>
                        <div class="d-flex gap-2 flex-wrap mb-2">
                            <span class="badge bg-secondary">{{ $program->code }}</span>
                            @if($program->abbreviation && $program->abbreviation !== $program->code)
                                <span class="badge bg-light text-dark border">{{ $program->abbreviation }}</span>
                            @endif
                            <span class="badge bg-info text-white">{{ ucfirst($program->system_type) }}</span>
                            @if($program->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </div>
                        @if($program->description)
                            <p class="text-muted small mb-0">{{ $program->description }}</p>
                        @endif
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.admission-config.index', $program) }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-sliders me-1"></i>Admission Config
                        </a>
                        <a href="{{ route('admin.programs.edit', $program) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </a>
                    </div>
                </div>
                <hr>
                <div class="row g-3 text-center">
                    <div class="col-3">
                        <div class="fw-bold fs-5">{{ $program->duration_years }}</div>
                        <small class="text-muted">Years</small>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold fs-5">{{ $program->total_terms }}</div>
                        <small class="text-muted">Total {{ $program->term_type_label }}s</small>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold fs-5">{{ $program->default_intake_capacity }}</div>
                        <small class="text-muted">Intake/Batch</small>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold fs-5">{{ $studentCount }}</div>
                        <small class="text-muted">Students</small>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted"><i class="bi bi-building me-1"></i>{{ $program->department->name ?? '—' }}</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Quick Stats</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Batches</span>
                    <strong>{{ $program->batches->count() }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Active Batches</span>
                    <strong>{{ $program->batches->where('status', 'active')->count() }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Specializations</span>
                    <strong>{{ $program->specializations->count() }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Students</span>
                    <strong>{{ $studentCount }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Seat Matrix Section --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="bi bi-grid-3x3-gap me-2"></i>Seat Matrix (Reservation)</h6>
        <a href="{{ route('admin.seat-matrix.index', $program) }}" class="btn btn-sm btn-outline-primary">
            Manage Seat Matrix
        </a>
    </div>
    <div class="card-body">
        @php $seatMatrix = $program->seatMatrices()->whereNull('batch_id')->first(); @endphp
        @if($seatMatrix)
        <div class="row g-2 text-center">
            @foreach(['general_seats' => 'General', 'obc_seats' => 'OBC', 'obc_nc_seats' => 'OBC-NC', 'sc_seats' => 'SC', 'st_seats' => 'ST', 'ews_seats' => 'EWS', 'pwd_seats' => 'PWD*', 'nri_seats' => 'NRI*', 'management_quota_seats' => 'Mgmt'] as $col => $lbl)
            <div class="col-4 col-sm-3 col-md-2 col-lg-1">
                <div class="border rounded p-1">
                    <div class="fw-bold text-primary">{{ $seatMatrix->$col }}</div>
                    <small class="text-muted" style="font-size:.7rem">{{ $lbl }}</small>
                </div>
            </div>
            @endforeach
            <div class="col-4 col-sm-3 col-md-2 col-lg-1">
                <div class="border rounded p-1 bg-light">
                    <div class="fw-bold">{{ $seatMatrix->total_seats }}</div>
                    <small class="text-muted" style="font-size:.7rem">Total</small>
                </div>
            </div>
        </div>
        <div class="mt-2" style="font-size:.7rem" class="text-muted">* Supernumerary seats</div>
        @else
        <p class="text-muted small mb-2">No seat matrix configured for this program.</p>
        <a href="{{ route('admin.seat-matrix.create', $program) }}" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Set Up Seat Matrix
        </a>
        @endif
    </div>
</div>

{{-- Specializations Section --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold">Specializations</h6>
    </div>
    <div class="card-body">
        @if($program->specializations->isNotEmpty())
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach($program->specializations as $spec)
                    <div class="d-flex align-items-center gap-2 border rounded px-3 py-2 bg-light">
                        <div>
                            <div class="fw-semibold" style="font-size:.85rem;">{{ $spec->name }}</div>
                            <small class="text-muted">{{ $spec->code }}</small>
                        </div>
                        <form method="POST" action="{{ route('admin.specializations.destroy', $spec) }}" class="d-inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-link text-danger p-0 ms-2" style="font-size:.75rem;"
                                onclick="return confirm('Remove specialization?')">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-muted small mb-3">No specializations added yet.</p>
        @endif

        {{-- Add specialization form --}}
        <form method="POST" action="{{ route('admin.programs.specializations.store', $program) }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-5">
                <label class="form-label form-label-sm">Specialization Name</label>
                <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Finance" required>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm">Code</label>
                <input type="text" name="code" class="form-control form-control-sm" placeholder="FIN" maxlength="20" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-plus-lg me-1"></i> Add
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Batches Section --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold">Batches</h6>
        <a href="{{ route('admin.batches.create') }}?program_id={{ $program->id }}" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Batch
        </a>
    </div>
    @if($program->batches->isEmpty())
        <div class="card-body text-center py-4">
            <p class="text-muted small mb-2">No batches for this program yet.</p>
            <a href="{{ route('admin.batches.create') }}?program_id={{ $program->id }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-plus-lg me-1"></i> Create First Batch
            </a>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Batch Name</th>
                        <th>Code</th>
                        <th>Academic Year</th>
                        <th>Dates</th>
                        <th>Capacity</th>
                        <th>Terms</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($program->batches as $batch)
                    @php
                        $statusBadge = match($batch->status) {
                            'upcoming'  => 'bg-info text-white',
                            'active'    => 'bg-success text-white',
                            'completed' => 'bg-secondary text-white',
                            'cancelled' => 'bg-danger text-white',
                            default     => 'bg-secondary text-white',
                        };
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $batch->name }}</td>
                        <td><code>{{ $batch->code }}</code></td>
                        <td>{{ $batch->academicYear->name ?? '—' }}</td>
                        <td>
                            <small>{{ $batch->start_date?->format('M Y') }} — {{ $batch->end_date?->format('M Y') }}</small>
                        </td>
                        <td>{{ $batch->intake_capacity }}</td>
                        <td>{{ $batch->terms->count() }}</td>
                        <td><span class="badge {{ $statusBadge }}">{{ ucfirst($batch->status) }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('admin.batches.show', $batch) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
