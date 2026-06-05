@extends('layouts.teacher')
@section('title', 'My Profile')
@section('page-title', 'My Profile')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('teacher.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">My Profile</li>
@endsection

@section('content')

<div class="row g-3">
    {{-- Left: Avatar & Summary --}}
    <div class="col-lg-4">
        <div class="card text-center">
            <div class="card-body pt-4">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle"
                    style="width:80px;height:80px;background:#10b981;color:white;font-size:2rem;font-weight:700">
                    {{ strtoupper(substr($teacher->user->name, 0, 2)) }}
                </div>
                <h5 class="fw-bold mb-0">{{ $teacher->user->name }}</h5>
                <div class="text-muted" style="font-size:.85rem">{{ $teacher->designation ?? 'Faculty' }}</div>
                <div class="text-muted" style="font-size:.82rem">{{ $teacher->user->email }}</div>
                <div class="mt-2">
                    <span class="badge badge-info">{{ $teacher->employee_id }}</span>
                </div>
            </div>
            <div class="card-body border-top p-0">
                <table class="table table-sm table-borderless mb-0" style="font-size:.84rem">
                    <tr><td class="text-muted ps-3">Department</td><td class="pe-3 fw-semibold">{{ $teacher->department->name ?? '–' }}</td></tr>
                    <tr><td class="text-muted ps-3">Employment</td><td class="pe-3">{{ ucfirst(str_replace('_', ' ', $teacher->employment_type ?? '–')) }}</td></tr>
                    <tr><td class="text-muted ps-3">Joined</td><td class="pe-3">{{ optional($teacher->date_of_joining)->format('d M Y') ?? '–' }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    {{-- Right: Tabs --}}
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
                {{-- Info Tab --}}
                <div class="tab-pane fade show active" id="tab-info">
                    <div class="card-body">
                        <table class="table table-sm table-borderless" style="font-size:.88rem">
                            <tr><td class="text-muted" style="width:40%">Phone</td><td>{{ $teacher->phone ?? '–' }}</td></tr>
                            <tr><td class="text-muted">Qualification</td><td>{{ $teacher->qualification ?? '–' }}</td></tr>
                            <tr><td class="text-muted">Specialization</td><td>{{ $teacher->specialization ?? '–' }}</td></tr>
                            <tr><td class="text-muted">Employment Type</td><td>{{ ucfirst(str_replace('_', ' ', $teacher->employment_type ?? '–')) }}</td></tr>
                            <tr><td class="text-muted">Date of Joining</td><td>{{ optional($teacher->date_of_joining)->format('d M Y') ?? '–' }}</td></tr>
                            <tr><td class="text-muted">Status</td><td>
                                <span class="badge {{ $teacher->status === 'active' ? 'badge-active' : 'badge-pending' }}">{{ ucfirst($teacher->status ?? 'active') }}</span>
                            </td></tr>
                        </table>
                    </div>
                </div>

                {{-- Edit Tab --}}
                <div class="tab-pane fade" id="tab-edit">
                    <div class="card-body">
                        <form method="POST" action="{{ route('teacher.profile.update') }}">
                            @csrf @method('PUT')

                            <div class="mb-3">
                                <label class="form-label form-label-sm">Designation</label>
                                <input type="text" name="designation" class="form-control form-control-sm @error('designation') is-invalid @enderror"
                                    value="{{ old('designation', $teacher->designation) }}" maxlength="100">
                                @error('designation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label form-label-sm">Phone</label>
                                <input type="text" name="phone" class="form-control form-control-sm @error('phone') is-invalid @enderror"
                                    value="{{ old('phone', $teacher->phone) }}" maxlength="20">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label form-label-sm">Qualification</label>
                                <input type="text" name="qualification" class="form-control form-control-sm @error('qualification') is-invalid @enderror"
                                    value="{{ old('qualification', $teacher->qualification) }}" maxlength="200">
                                @error('qualification')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label form-label-sm">Specialization</label>
                                <input type="text" name="specialization" class="form-control form-control-sm @error('specialization') is-invalid @enderror"
                                    value="{{ old('specialization', $teacher->specialization) }}" maxlength="200">
                                @error('specialization')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
