@extends('layouts.admin')
@section('title', 'Edit Admission')
@section('page-title', 'Admissions')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.admissions.index') }}">Admissions</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')

<div class="card">
    <div class="card-header">
        <span class="fw-600"><i class="bi bi-pencil-fill me-2 text-primary"></i>Edit Admission — {{ $admission->applicant_name }}</span>
    </div>
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form action="{{ route('admin.admissions.update', $admission) }}" method="POST">
            @csrf @method('PUT')

            {{-- Personal Details --}}
            <h6 class="fw-bold text-muted mb-3 mt-2"><i class="bi bi-person me-1"></i>Personal Details</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Applicant Name <span class="text-danger">*</span></label>
                    <input type="text" name="applicant_name" class="form-control @error('applicant_name') is-invalid @enderror" value="{{ old('applicant_name', $admission->applicant_name) }}" required>
                    @error('applicant_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $admission->email) }}" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $admission->phone) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Father's Name</label>
                    <input type="text" name="father_name" class="form-control" value="{{ old('father_name', $admission->father_name) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mother's Name</label>
                    <input type="text" name="mother_name" class="form-control" value="{{ old('mother_name', $admission->mother_name) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control @error('date_of_birth') is-invalid @enderror" value="{{ old('date_of_birth', $admission->date_of_birth?->format('Y-m-d')) }}">
                    @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">Select</option>
                        @foreach(['male','female','other'] as $g)
                            <option value="{{ $g }}" @selected(old('gender',$admission->gender)==$g)>{{ ucfirst($g) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">Select</option>
                        @foreach(['general'=>'General','obc'=>'OBC','sc'=>'SC','st'=>'ST'] as $val=>$label)
                            <option value="{{ $val }}" @selected(old('category',$admission->category)==$val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2">{{ old('address', $admission->address) }}</textarea>
                </div>
            </div>

            <hr>
            {{-- Academic Background --}}
            <h6 class="fw-bold text-muted mb-3"><i class="bi bi-mortarboard me-1"></i>Academic Background</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Course <span class="text-danger">*</span></label>
                    <select name="course_id" class="form-select @error('course_id') is-invalid @enderror" required>
                        <option value="">Select Course</option>
                        @foreach($courses as $c)
                            <option value="{{ $c->id }}" @selected(old('course_id',$admission->course_id)==$c->id)>{{ $c->name }} ({{ $c->code }})</option>
                        @endforeach
                    </select>
                    @error('course_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Last Qualification</label>
                    <input type="text" name="last_qualification" class="form-control" value="{{ old('last_qualification', $admission->last_qualification) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Last Institution</label>
                    <input type="text" name="last_institution" class="form-control" value="{{ old('last_institution', $admission->last_institution) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Last Percentage</label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" max="100" name="last_percentage" class="form-control @error('last_percentage') is-invalid @enderror" value="{{ old('last_percentage', $admission->last_percentage) }}">
                        <span class="input-group-text">%</span>
                        @error('last_percentage')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <hr>
            {{-- Application Details --}}
            <h6 class="fw-bold text-muted mb-3"><i class="bi bi-clipboard-check me-1"></i>Application Details</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label">Application Date</label>
                    <input type="date" name="application_date" class="form-control" value="{{ old('application_date', $admission->application_date?->format('Y-m-d')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                        @foreach(['enquiry','applied','shortlisted','admitted','rejected','withdrawn'] as $s)
                            <option value="{{ $s }}" @selected(old('status',$admission->status)==$s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $admission->remarks) }}</textarea>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update</button>
                <a href="{{ route('admin.admissions.show', $admission) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

@endsection
