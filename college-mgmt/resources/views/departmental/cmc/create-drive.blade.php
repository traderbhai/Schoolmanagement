@extends('layouts.admin')
@section('title','New Placement Drive')
@section('page-title','New Placement Drive')
@section('content')
<div class="row justify-content-center"><div class="col-lg-9">
<div class="card">
  <div class="card-header bg-transparent fw-semibold">Create Placement Drive</div>
  <div class="card-body">
    <div class="alert alert-info small py-2">
      Use draft/upcoming status until company, eligibility, application deadline, and drive date are confirmed. Published drive details become student-facing.
    </div>
    <form method="POST" action="{{ route('cmc.drives.store') }}" onsubmit="return confirm('Create this placement drive with the selected status and dates? Confirm company, eligibility, application deadline, student visibility, and communication readiness before saving.')">
      @csrf
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label small fw-semibold">Drive Title <span class="text-danger">*</span></label>
          <input aria-label="Placement drive title" type="text" name="title" class="form-control" value="{{ old('title') }}" required placeholder="e.g. Software Engineer Campus Drive">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Status <span class="text-danger">*</span></label>
          <select aria-label="Status" name="status" class="form-select" required>
            @foreach(['upcoming','ongoing','completed','cancelled'] as $s)
            <option value="{{ $s }}" {{ old('status','upcoming')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Company <span class="text-danger">*</span></label>
          <select aria-label="Company" name="company_id" class="form-select" required>
            <option value="">Select Company</option>
            @foreach($companies as $c)
            <option value="{{ $c->id }}" {{ old('company_id')==$c->id?'selected':'' }}>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Job Role <span class="text-danger">*</span></label>
          <input aria-label="Job role" type="text" name="job_role" class="form-control" value="{{ old('job_role') }}" required placeholder="e.g. Software Engineer">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Package (CTC)</label>
          <input aria-label="Compensation package" type="text" name="package" class="form-control" value="{{ old('package') }}" placeholder="e.g. 5-8 LPA">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Min CGPA</label>
          <input aria-label="Minimum CGPA" type="number" name="min_cgpa" class="form-control" value="{{ old('min_cgpa') }}" min="0" max="10" step="0.1" placeholder="e.g. 6.5">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Vacancies</label>
          <input aria-label="Number of vacancies" type="number" name="vacancies" class="form-control" value="{{ old('vacancies') }}" min="1" placeholder="e.g. 10">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Drive Date <span class="text-danger">*</span></label>
          <input aria-label="Drive Date" type="date" name="drive_date" class="form-control" value="{{ old('drive_date') }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Last Apply Date</label>
          <input aria-label="Last Apply Date" type="date" name="last_apply_date" class="form-control" value="{{ old('last_apply_date') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Location</label>
          <input aria-label="Job location" type="text" name="location" class="form-control" value="{{ old('location') }}" placeholder="e.g. Bengaluru">
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Eligibility Criteria</label>
          <textarea aria-label="Placement eligibility criteria" name="eligibility" class="form-control" rows="2" placeholder="e.g. B.Tech CSE/IT, 60% throughout...">{{ old('eligibility') }}</textarea>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Description</label>
          <textarea aria-label="Placement drive description" name="description" class="form-control" rows="3" placeholder="Additional details about the drive...">{{ old('description') }}</textarea>
        </div>
        <div class="col-12 d-flex gap-2 pt-2">
          <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Drive</button>
          <a href="{{ route('cmc.drives') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
</div></div>
@endsection
