@extends('layouts.admin')
@section('title','New Placement Drive')
@section('page-title','New Placement Drive')
@section('content')
<div class="row justify-content-center"><div class="col-lg-9">
<div class="card">
  <div class="card-header bg-transparent fw-semibold">Create Placement Drive</div>
  <div class="card-body">
    <form method="POST" action="{{ route('cmc.drives.store') }}">
      @csrf
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label small fw-semibold">Drive Title <span class="text-danger">*</span></label>
          <input type="text" name="title" class="form-control" value="{{ old('title') }}" required placeholder="e.g. Software Engineer Campus Drive">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Status <span class="text-danger">*</span></label>
          <select name="status" class="form-select" required>
            @foreach(['upcoming','ongoing','completed','cancelled'] as $s)
            <option value="{{ $s }}" {{ old('status','upcoming')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Company <span class="text-danger">*</span></label>
          <select name="company_id" class="form-select" required>
            <option value="">— Select Company —</option>
            @foreach($companies as $c)
            <option value="{{ $c->id }}" {{ old('company_id')==$c->id?'selected':'' }}>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Job Role <span class="text-danger">*</span></label>
          <input type="text" name="job_role" class="form-control" value="{{ old('job_role') }}" required placeholder="e.g. Software Engineer">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Package (CTC)</label>
          <input type="text" name="package" class="form-control" value="{{ old('package') }}" placeholder="e.g. 5-8 LPA">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Min CGPA</label>
          <input type="number" name="min_cgpa" class="form-control" value="{{ old('min_cgpa') }}" min="0" max="10" step="0.1" placeholder="e.g. 6.5">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Vacancies</label>
          <input type="number" name="vacancies" class="form-control" value="{{ old('vacancies') }}" min="1" placeholder="e.g. 10">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Drive Date <span class="text-danger">*</span></label>
          <input type="date" name="drive_date" class="form-control" value="{{ old('drive_date') }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Last Apply Date</label>
          <input type="date" name="last_apply_date" class="form-control" value="{{ old('last_apply_date') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Location</label>
          <input type="text" name="location" class="form-control" value="{{ old('location') }}" placeholder="e.g. Bengaluru">
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Eligibility Criteria</label>
          <textarea name="eligibility" class="form-control" rows="2" placeholder="e.g. B.Tech CSE/IT, 60% throughout...">{{ old('eligibility') }}</textarea>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Description</label>
          <textarea name="description" class="form-control" rows="3" placeholder="Additional details about the drive...">{{ old('description') }}</textarea>
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
