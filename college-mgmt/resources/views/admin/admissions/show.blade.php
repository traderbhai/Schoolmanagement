@extends('layouts.admin')
@section('title', $admission->applicant_name)
@section('page-title', 'Admissions')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.admissions.index') }}">Admissions</a></li>
    <li class="breadcrumb-item active">{{ $admission->applicant_name }}</li>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">
    {{-- Left: Profile card --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center pt-4 pb-3">
                <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;font-size:2rem;color:var(--clr-primary)">
                    <i class="bi bi-person-fill"></i>
                </div>
                <h5 class="fw-bold mb-1">{{ $admission->applicant_name }}</h5>
                @php
                    $badgeClass = match($admission->status) {
                        'enquiry'     => 'badge-info',
                        'applied'     => 'badge-pending',
                        'shortlisted' => 'bg-warning text-dark',
                        'admitted'    => 'badge-active',
                        'rejected'    => 'badge-danger',
                        'withdrawn'   => 'bg-secondary text-white',
                        default       => 'bg-secondary text-white',
                    };
                @endphp
                <span class="badge {{ $badgeClass }} mb-3">{{ ucfirst($admission->status) }}</span>

                <div class="text-start border-top pt-3">
                    <div class="mb-2 d-flex gap-2 align-items-start">
                        <i class="bi bi-envelope text-muted mt-1"></i>
                        <span style="word-break:break-all">{{ $admission->email }}</span>
                    </div>
                    @if($admission->phone)
                    <div class="mb-2 d-flex gap-2 align-items-start">
                        <i class="bi bi-telephone text-muted mt-1"></i>
                        <span>{{ $admission->phone }}</span>
                    </div>
                    @endif
                    @if($admission->course)
                    <div class="mb-2 d-flex gap-2 align-items-start">
                        <i class="bi bi-journal-bookmark text-muted mt-1"></i>
                        <span>{{ $admission->course->name }}</span>
                    </div>
                    @endif
                    @if($admission->application_date)
                    <div class="mb-2 d-flex gap-2 align-items-start">
                        <i class="bi bi-calendar-event text-muted mt-1"></i>
                        <span>Applied: {{ $admission->application_date->format('d M Y') }}</span>
                    </div>
                    @endif
                    @if($admission->remarks)
                    <div class="mb-2 d-flex gap-2 align-items-start">
                        <i class="bi bi-chat-left-text text-muted mt-1"></i>
                        <span class="text-muted fst-italic">{{ $admission->remarks }}</span>
                    </div>
                    @endif
                </div>

                <div class="d-flex gap-2 mt-3">
                    <a href="{{ route('admin.admissions.edit', $admission) }}" class="btn btn-outline-primary btn-sm flex-fill">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                    <form action="{{ route('admin.admissions.destroy', $admission) }}" method="POST" onsubmit="return confirm('Delete this record?')" class="flex-fill">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="bi bi-trash me-1"></i>Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>

        @if($admission->converted_student_id)
        <div class="card mt-3">
            <div class="card-body text-center">
                <i class="bi bi-patch-check-fill text-success fs-3 mb-2 d-block"></i>
                <div class="fw-semibold">Student Account Created</div>
                <a href="{{ route('admin.students.show', $admission->converted_student_id) }}" class="btn btn-sm btn-success mt-2">
                    <i class="bi bi-person-lines-fill me-1"></i>View Student Record
                </a>
            </div>
        </div>
        @endif
    </div>

    {{-- Right: Tabs --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="admTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#academic">Academic</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#personal">Personal</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#actions">Actions</a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    {{-- Academic Tab --}}
                    <div class="tab-pane fade show active" id="academic">
                        <dl class="row mb-0">
                            <dt class="col-sm-5 text-muted">Course</dt>
                            <dd class="col-sm-7">{{ $admission->course?->name ?? '—' }}</dd>
                            <dt class="col-sm-5 text-muted">Last Qualification</dt>
                            <dd class="col-sm-7">{{ $admission->last_qualification ?? '—' }}</dd>
                            <dt class="col-sm-5 text-muted">Last Institution</dt>
                            <dd class="col-sm-7">{{ $admission->last_institution ?? '—' }}</dd>
                            <dt class="col-sm-5 text-muted">Last Percentage</dt>
                            <dd class="col-sm-7">{{ $admission->last_percentage !== null ? $admission->last_percentage.'%' : '—' }}</dd>
                            <dt class="col-sm-5 text-muted">Category</dt>
                            <dd class="col-sm-7">{{ $admission->category ? strtoupper($admission->category) : '—' }}</dd>
                        </dl>
                    </div>

                    {{-- Personal Tab --}}
                    <div class="tab-pane fade" id="personal">
                        <dl class="row mb-0">
                            <dt class="col-sm-5 text-muted">Date of Birth</dt>
                            <dd class="col-sm-7">{{ $admission->date_of_birth?->format('d M Y') ?? '—' }}</dd>
                            <dt class="col-sm-5 text-muted">Gender</dt>
                            <dd class="col-sm-7">{{ $admission->gender ? ucfirst($admission->gender) : '—' }}</dd>
                            <dt class="col-sm-5 text-muted">Father's Name</dt>
                            <dd class="col-sm-7">{{ $admission->father_name ?? '—' }}</dd>
                            <dt class="col-sm-5 text-muted">Mother's Name</dt>
                            <dd class="col-sm-7">{{ $admission->mother_name ?? '—' }}</dd>
                            <dt class="col-sm-5 text-muted">Address</dt>
                            <dd class="col-sm-7">{{ $admission->address ?? '—' }}</dd>
                        </dl>
                    </div>

                    {{-- Actions Tab --}}
                    <div class="tab-pane fade" id="actions">
                        {{-- Status Update Form --}}
                        <div class="mb-4">
                            <h6 class="fw-semibold mb-3">Update Status</h6>
                            <form action="{{ route('admin.admissions.status', $admission) }}" method="POST">
                                @csrf @method('PATCH')
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label class="form-label form-label-sm">Status</label>
                                        <select name="status" class="form-select form-select-sm">
                                            @foreach(['enquiry','applied','shortlisted','admitted','rejected','withdrawn'] as $s)
                                                <option value="{{ $s }}" @selected($admission->status==$s)>{{ ucfirst($s) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label form-label-sm">Remarks</label>
                                        <textarea name="remarks" class="form-control form-control-sm" rows="2">{{ $admission->remarks }}</textarea>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary mt-3">
                                    <i class="bi bi-arrow-repeat me-1"></i>Update Status
                                </button>
                            </form>
                        </div>

                        {{-- Convert to Student --}}
                        @if(!in_array($admission->status, ['admitted','rejected']) && !$admission->converted_student_id)
                        <hr>
                        <div>
                            <h6 class="fw-semibold mb-1">Convert to Student</h6>
                            <p class="text-muted small mb-3">This will create a user account and student record from this admission data.</p>
                            <form action="{{ route('admin.admissions.convert', $admission) }}" method="POST">
                                @csrf
                                @php $departments = \App\Models\Department::where('is_active',true)->get(); @endphp
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label class="form-label form-label-sm">Enrollment Number <span class="text-danger">*</span></label>
                                        <input type="text" name="enrollment_number" class="form-control form-control-sm @error('enrollment_number') is-invalid @enderror" required>
                                        @error('enrollment_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label form-label-sm">Department <span class="text-danger">*</span></label>
                                        <select name="department_id" class="form-select form-select-sm @error('department_id') is-invalid @enderror" required>
                                            <option value="">Select Department</option>
                                            @foreach($departments as $d)
                                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-sm btn-success mt-3">
                                    <i class="bi bi-person-check-fill me-1"></i>Admit &amp; Create Student Account
                                </button>
                            </form>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
