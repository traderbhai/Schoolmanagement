@extends('layouts.admin')
@section('title','Add Company')
@section('page-title','Add Company')
@section('content')
<div class="row justify-content-center"><div class="col-lg-7">
<div class="card">
  <div class="card-header bg-transparent fw-semibold">New Company</div>
  <div class="card-body">
    <div class="alert alert-info small py-2">
      Add verified recruiter contact details so drives, applications, and follow-ups remain traceable.
    </div>
    <form method="POST" action="{{ route('cmc.companies.store') }}" onsubmit="return confirm('Add this company to the recruiter database?')">
      @csrf
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label small fw-semibold">Company Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Industry</label>
          <input type="text" name="industry" class="form-control" value="{{ old('industry') }}" placeholder="e.g. IT, Finance">
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Website</label>
          <input type="url" name="website" class="form-control" value="{{ old('website') }}" placeholder="https://...">
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Contact Person</label>
          <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person') }}">
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Contact Email</label>
          <input type="email" name="contact_email" class="form-control" value="{{ old('contact_email') }}">
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Contact Phone</label>
          <input type="text" name="contact_phone" class="form-control" value="{{ old('contact_phone') }}">
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Description</label>
          <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
        </div>
        <div class="col-12 d-flex gap-2 pt-2">
          <button type="submit" class="btn btn-primary">Add Company</button>
          <a href="{{ route('cmc.companies') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
</div></div>
@endsection
