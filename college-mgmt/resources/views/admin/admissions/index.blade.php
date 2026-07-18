@extends('layouts.admin')
@section('title', 'Admissions')
@section('page-title', 'Admissions')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Admissions</li>
@endsection

@section('content')

{{-- Page Header --}}
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Admission Management</h5>
        <div class="text-muted" style="font-size:.82rem">Track enquiries and manage the admission pipeline</div>
    </div>
    <a href="{{ route('admin.admissions.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i>Add Enquiry
    </a>
</div>

{{-- KPI Row --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-blue">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Enquiry</div>
                    <div class="kpi-value mt-1">{{ $statusCounts['enquiry'] }}</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-question-circle-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-purple">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Applied</div>
                    <div class="kpi-value mt-1">{{ $statusCounts['applied'] }}</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-amber">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Shortlisted</div>
                    <div class="kpi-value mt-1">{{ $statusCounts['shortlisted'] }}</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-funnel-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-green">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Admitted</div>
                    <div class="kpi-value mt-1">{{ $statusCounts['admitted'] }}</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-patch-check-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card kpi-red">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Rejected</div>
                    <div class="kpi-value mt-1">{{ $statusCounts['rejected'] }}</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-x-circle-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card" style="background:var(--bs-secondary-bg,#f8f9fa)">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Withdrawn</div>
                    <div class="kpi-value mt-1">{{ $statusCounts['withdrawn'] }}</div>
                </div>
                <div class="kpi-icon bg-secondary"><i class="bi bi-dash-circle-fill"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Filter Bar --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-4">
                <label class="form-label form-label-sm mb-1">Search</label>
                <input aria-label="Admission search" type="text" name="search" class="form-control form-control-sm" placeholder="Name, email, phone…" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm mb-1">Status</label>
                <select aria-label="Status" name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    @foreach(['enquiry','applied','shortlisted','admitted','rejected','withdrawn'] as $s)
                        <option value="{{ $s }}" @selected(request('status')==$s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Course</label>
                <select aria-label="Course" name="course_id" class="form-select form-select-sm">
                    <option value="">All Courses</option>
                    @foreach($courses as $c)
                        <option value="{{ $c->id }}" @selected(request('course_id')==$c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-primary btn-sm">Filter</button>
                @if(request()->hasAny(['search','status','course_id']))
                    <a href="{{ route('admin.admissions.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Data Table --}}
<div class="card">
    <div class="card-body p-0">
        @if($admissions->isEmpty())
            <div class="empty-state py-5 text-center text-muted">
                <i class="bi bi-person-plus fs-1 d-block mb-3"></i>
                <div class="fw-semibold">No admissions found</div>
                <div class="text-sm">Add your first enquiry to get started.</div>
            </div>
        @else
        <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Phone</th>
                    <th scope="col">Course</th>
                    <th scope="col">Last Qual %</th>
                    <th scope="col">App. Date</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($admissions as $i => $adm)
                <tr>
                    <td>{{ $admissions->firstItem() + $i }}</td>
                    <td>
                        <a href="{{ route('admin.admissions.show', $adm) }}" class="fw-semibold text-decoration-none">
                            {{ $adm->applicant_name }}
                        </a>
                    </td>
                    <td>{{ $adm->email }}</td>
                    <td>{{ $adm->phone ?? '—' }}</td>
                    <td>{{ $adm->course?->code ?? '—' }}</td>
                    <td>{{ $adm->last_percentage !== null ? $adm->last_percentage.'%' : '—' }}</td>
                    <td>{{ $adm->application_date?->format('d M Y') ?? '—' }}</td>
                    <td>
                        @php
                            $badgeClass = match($adm->status) {
                                'enquiry'     => 'badge-info',
                                'applied'     => 'badge-pending',
                                'shortlisted' => 'bg-warning text-dark',
                                'admitted'    => 'badge-active',
                                'rejected'    => 'badge-danger',
                                'withdrawn'   => 'bg-secondary text-white',
                                default       => 'bg-secondary text-white',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ ucfirst($adm->status) }}</span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-1 flex-wrap">
                            <a href="{{ route('admin.admissions.show', $adm) }}" class="btn btn-xs btn-outline-primary" title="View" aria-label="View admission record for {{ $adm->student_name ?? $adm->name ?? 'student' }}">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('admin.admissions.edit', $adm) }}" class="btn btn-xs btn-outline-secondary" title="Edit" aria-label="Edit admission record for {{ $adm->student_name ?? $adm->name ?? 'student' }}">
                                <i class="bi bi-pencil"></i>
                            </a>
                            {{-- Quick Status Change --}}
                            <form action="{{ route('admin.admissions.status', $adm) }}" method="POST" class="d-inline">
                                @csrf @method('PATCH')
                                <select aria-label="Status" name="status" class="form-select form-select-sm d-inline-block" style="width:auto;font-size:.75rem;padding:.2rem .4rem" onchange="this.form.submit()" title="Change status">
                                    @foreach(['enquiry','applied','shortlisted','admitted','rejected','withdrawn'] as $s)
                                        <option value="{{ $s }}" @selected($adm->status == $s)>{{ ucfirst($s) }}</option>
                                    @endforeach
                                </select>
                            </form>
                            <form action="{{ route('admin.admissions.destroy', $adm) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete admission record for {{ addslashes($adm->student_name ?? $adm->name ?? 'this student') }}? Confirm this record is not needed for applicant history, enrollment audit, fee records, or reports.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-xs btn-outline-danger" title="Delete" aria-label="Delete admission record for {{ $adm->student_name ?? $adm->name ?? 'student' }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <div class="px-3 py-2">
            {{ $admissions->links() }}
        </div>
        @endif
    </div>
</div>

@endsection
