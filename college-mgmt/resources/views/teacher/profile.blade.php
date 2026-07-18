@extends('layouts.teacher')
@section('title', 'My Profile')
@section('page-title', 'My Profile')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('teacher.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">My Profile</li>
@endsection

@section('content')

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card text-center">
            <div class="card-body pt-4">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle"
                     style="width:80px;height:80px;background:#10b981;color:white;font-size:2rem;font-weight:700">
                    {{ strtoupper(substr($teacher->user->name, 0, 2)) }}
                </div>
                <h5 class="fw-bold mb-0">{{ $teacher->user->name }}</h5>
                <div class="text-muted" style="font-size:.85rem">{{ $teacher->designation ?? 'Designation not updated' }}</div>
                <div class="text-muted" style="font-size:.82rem">{{ $teacher->user->email }}</div>
                <div class="mt-2">
                    <span class="badge badge-info">{{ $teacher->employee_id ?: 'Employee ID pending' }}</span>
                </div>
            </div>
            <div class="card-body border-top p-0">
                <table class="table table-sm table-borderless mb-0" style="font-size:.84rem">
                    <tr>
                        <td class="text-muted ps-3">Department</td>
                        <td class="pe-3 fw-semibold">{{ $teacher->department->name ?? 'Department not linked' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-3">Employment</td>
                        <td class="pe-3">{{ $teacher->employment_type ? ucfirst(str_replace('_', ' ', $teacher->employment_type)) : 'Employment type not updated' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-3">Joined</td>
                        <td class="pe-3">{{ optional($teacher->date_of_joining)->format('d M Y') ?? 'Joining date not updated' }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header p-0">
                <ul class="nav nav-tabs card-header-tabs ms-0" id="profileTabs">
                    <li class="nav-item">
                        <a class="nav-link active px-4" data-bs-toggle="tab" href="#tab-info">
                            <i class="bi bi-person me-1"></i>Profile Info
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-4" data-bs-toggle="tab" href="#tab-edit">
                            <i class="bi bi-pencil me-1"></i>Update Details
                        </a>
                    </li>
                </ul>
            </div>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-info">
                    <div class="card-body">
                        <div class="alert alert-info small">
                            <strong>Profile data ownership:</strong>
                            You can update contact, designation, qualification, and specialization here.
                            Department, employee ID, employment type, joining date, and active/inactive status are maintained by administration because they control timetable, leave, mentoring, and result-entry access.
                        </div>

                        <table class="table table-sm table-borderless" style="font-size:.88rem">
                            <tr>
                                <td class="text-muted" style="width:40%">Phone</td>
                                <td>{{ $teacher->phone ?? 'Phone not updated' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Qualification</td>
                                <td>{{ $teacher->qualification ?? 'Qualification not updated' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Specialization</td>
                                <td>{{ $teacher->specialization ?? 'Specialization not updated' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Employment Type</td>
                                <td>{{ $teacher->employment_type ? ucfirst(str_replace('_', ' ', $teacher->employment_type)) : 'Employment type not updated' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Date of Joining</td>
                                <td>{{ optional($teacher->date_of_joining)->format('d M Y') ?? 'Joining date not updated' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status</td>
                                <td>
                                    <span class="badge {{ $teacher->status === 'active' ? 'badge-active' : 'badge-pending' }}">{{ ucfirst($teacher->status ?? 'active') }}</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-edit">
                    <div class="card-body">
                        @if($teacher->status !== 'active')
                            <div class="alert alert-warning small">
                                Profile updates are locked because this teacher profile is not active. Contact administration if this status is incorrect.
                            </div>
                        @else
                            <div class="alert alert-light border small">
                                Keep these fields current so students, mentors, Program Leadership, and PMC can identify the right contact and expertise for teaching, mentoring, and review workflows.
                            </div>
                        @endif

                        <form method="POST" action="{{ route('teacher.profile.update') }}">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label class="form-label form-label-sm">Designation</label>
                                <input aria-label="Designation" type="text" name="designation" class="form-control form-control-sm @error('designation') is-invalid @enderror"
                                       value="{{ old('designation', $teacher->designation) }}" maxlength="100" @disabled($teacher->status !== 'active')>
                                @error('designation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label form-label-sm">Phone</label>
                                <input aria-label="Phone" type="text" name="phone" class="form-control form-control-sm @error('phone') is-invalid @enderror"
                                       value="{{ old('phone', $teacher->phone) }}" maxlength="20" @disabled($teacher->status !== 'active')>
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label form-label-sm">Qualification</label>
                                <input aria-label="Qualification" type="text" name="qualification" class="form-control form-control-sm @error('qualification') is-invalid @enderror"
                                       value="{{ old('qualification', $teacher->qualification) }}" maxlength="200" @disabled($teacher->status !== 'active')>
                                @error('qualification')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label form-label-sm">Specialization</label>
                                <input aria-label="Specialization" type="text" name="specialization" class="form-control form-control-sm @error('specialization') is-invalid @enderror"
                                       value="{{ old('specialization', $teacher->specialization) }}" maxlength="200" @disabled($teacher->status !== 'active')>
                                @error('specialization')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm" @disabled($teacher->status !== 'active')>
                                <i class="bi bi-save me-1"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
