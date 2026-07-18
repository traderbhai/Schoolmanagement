@extends('layouts.admin')
@section('title','Edit Drive')
@section('page-title','Edit Drive')
@section('content')
<div class="row justify-content-center"><div class="col-lg-9">
<div class="card">
  <div class="card-header bg-transparent fw-semibold">Edit Placement Drive</div>
  <div class="card-body">
    <div class="alert alert-warning small py-2">
      Changing an active drive can affect student applications and recruiter communication. Review dates, eligibility, and status before saving.
    </div>
    <form method="POST" action="{{ route('cmc.drives.update', $drive) }}" onsubmit="return confirm('Save placement drive changes? Confirm recruiter, dates, eligibility, application status, and student communication impact before updating this drive.')">
      @csrf @method('PUT')
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label small fw-semibold">Drive Title <span class="text-danger">*</span></label>
          <input aria-label="Title" type="text" name="title" class="form-control" value="{{ old('title',$drive->title) }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Status <span class="text-danger">*</span></label>
          <select aria-label="Status" name="status" class="form-select" required>
            @foreach(['upcoming','ongoing','completed','cancelled'] as $s)
            <option value="{{ $s }}" {{ old('status',$drive->status)===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Company <span class="text-danger">*</span></label>
          <select aria-label="Company" name="company_id" class="form-select" required>
            <option value="">Select Company</option>
            @foreach($companies as $c)
            <option value="{{ $c->id }}" {{ old('company_id',$drive->company_id)==$c->id?'selected':'' }}>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Job Role <span class="text-danger">*</span></label>
          <input aria-label="Job Role" type="text" name="job_role" class="form-control" value="{{ old('job_role',$drive->job_role) }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Package</label>
          <input aria-label="Package" type="text" name="package" class="form-control" value="{{ old('package',$drive->package) }}">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Min CGPA</label>
          <input aria-label="Min Cgpa" type="number" name="min_cgpa" class="form-control" value="{{ old('min_cgpa',$drive->min_cgpa) }}" min="0" max="10" step="0.1">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Vacancies</label>
          <input aria-label="Vacancies" type="number" name="vacancies" class="form-control" value="{{ old('vacancies',$drive->vacancies) }}" min="1">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Drive Date <span class="text-danger">*</span></label>
          <input aria-label="Drive Date" type="date" name="drive_date" class="form-control" value="{{ old('drive_date',$drive->drive_date?->format('Y-m-d')) }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Last Apply Date</label>
          <input aria-label="Last Apply Date" type="date" name="last_apply_date" class="form-control" value="{{ old('last_apply_date',$drive->last_apply_date?->format('Y-m-d')) }}">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Location</label>
          <input aria-label="Location" type="text" name="location" class="form-control" value="{{ old('location',$drive->location) }}">
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Eligibility</label>
          <textarea aria-label="Eligibility" name="eligibility" class="form-control" rows="2">{{ old('eligibility',$drive->eligibility) }}</textarea>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Description</label>
          <textarea aria-label="Description" name="description" class="form-control" rows="3">{{ old('description',$drive->description) }}</textarea>
        </div>
        <div class="col-12 d-flex gap-2 pt-2">
          <button type="submit" class="btn btn-primary">Update Drive</button>
          <a href="{{ route('cmc.drives') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
</div></div>
@endsection
