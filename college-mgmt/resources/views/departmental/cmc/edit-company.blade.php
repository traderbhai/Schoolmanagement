@extends('layouts.admin')
@section('title','Edit Company')
@section('page-title','Edit Company')
@section('content')
<div class="row justify-content-center"><div class="col-lg-7">
<div class="card">
  <div class="card-header bg-transparent fw-semibold">Edit Company</div>
  <div class="card-body">
    <form method="POST" action="{{ route('cmc.companies.update', $company) }}">
      @csrf @method('PUT')
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label small fw-semibold">Company Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" value="{{ old('name',$company->name) }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Industry</label>
          <input type="text" name="industry" class="form-control" value="{{ old('industry',$company->industry) }}">
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Website</label>
          <input type="url" name="website" class="form-control" value="{{ old('website',$company->website) }}">
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Contact Person</label>
          <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person',$company->contact_person) }}">
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Contact Email</label>
          <input type="email" name="contact_email" class="form-control" value="{{ old('contact_email',$company->contact_email) }}">
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Contact Phone</label>
          <input type="text" name="contact_phone" class="form-control" value="{{ old('contact_phone',$company->contact_phone) }}">
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Description</label>
          <textarea name="description" class="form-control" rows="3">{{ old('description',$company->description) }}</textarea>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
                {{ old('is_active',$company->is_active) ? 'checked' : '' }}>
            <label class="form-check-label small" for="isActive">Active (visible for placement drives)</label>
          </div>
        </div>
        <div class="col-12 d-flex gap-2 pt-2">
          <button type="submit" class="btn btn-primary">Update Company</button>
          <a href="{{ route('cmc.companies') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
</div></div>
@endsection
