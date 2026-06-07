@extends('layouts.admin')
@section('title','New Career Event')
@section('page-title','New Career Event')
@section('content')
<div class="row justify-content-center"><div class="col-lg-7">
<div class="card">
  <div class="card-header bg-transparent fw-semibold">Create Career Event</div>
  <div class="card-body">
    <form method="POST" action="{{ route('cmc.events.store') }}">
      @csrf
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label small fw-semibold">Event Title <span class="text-danger">*</span></label>
          <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Event Type <span class="text-danger">*</span></label>
          <select name="event_type" class="form-select" required>
            @foreach(['seminar','workshop','job-fair','guest-lecture','other'] as $t)
            <option value="{{ $t }}" {{ old('event_type')===$t?'selected':'' }}>{{ ucwords(str_replace('-',' ',$t)) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Event Date <span class="text-danger">*</span></label>
          <input type="date" name="event_date" class="form-control" value="{{ old('event_date') }}" required>
        </div>
        <div class="col-md-8">
          <label class="form-label small fw-semibold">Venue</label>
          <input type="text" name="venue" class="form-control" value="{{ old('venue') }}" placeholder="e.g. Seminar Hall A">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Seats</label>
          <input type="number" name="seats" class="form-control" value="{{ old('seats') }}" min="1" placeholder="Leave blank for unlimited">
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Registration Deadline</label>
          <input type="date" name="registration_deadline" class="form-control" value="{{ old('registration_deadline') }}">
        </div>
        <div class="col-md-6 d-flex align-items-end">
          <div class="form-check mb-2">
            <input type="checkbox" name="is_published" value="1" class="form-check-input" id="isPublished" {{ old('is_published') ? 'checked' : '' }}>
            <label class="form-check-label small" for="isPublished">Publish (visible to students)</label>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Description</label>
          <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
        </div>
        <div class="col-12 d-flex gap-2 pt-2">
          <button type="submit" class="btn btn-primary">Create Event</button>
          <a href="{{ route('cmc.events') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
</div></div>
@endsection
