@extends('layouts.admin')
@section('title','Edit Event')
@section('page-title','Edit Career Event')
@section('content')
<div class="row justify-content-center"><div class="col-lg-7">
<div class="card">
  <div class="card-header bg-transparent fw-semibold">Edit Event</div>
  <div class="card-body">
    <div class="alert alert-warning small py-2">
      If students have registered, date/type/venue/registration deadline changes are restricted by the system. Review published visibility before saving.
    </div>
    <form method="POST" action="{{ route('cmc.events.update', $event) }}" onsubmit="return confirm('Save career event changes? Confirm registrations, event date/type/venue, registration deadline, and published student visibility before updating.')">
      @csrf @method('PUT')
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label small fw-semibold">Event Title <span class="text-danger">*</span></label>
          <input aria-label="Title" type="text" name="title" class="form-control" value="{{ old('title',$event->title) }}" required>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Event Type <span class="text-danger">*</span></label>
          <select aria-label="Event Type" name="event_type" class="form-select" required>
            @foreach(\App\Models\CareerEvent::TYPE_LABELS as $t => $label)
            <option value="{{ $t }}" {{ old('event_type',$event->event_type)===$t?'selected':'' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Event Date <span class="text-danger">*</span></label>
          <input aria-label="Event Date" type="date" name="event_date" class="form-control" value="{{ old('event_date',$event->event_date?->format('Y-m-d')) }}" required>
        </div>
        <div class="col-md-8">
          <label class="form-label small fw-semibold">Venue</label>
          <input aria-label="Venue" type="text" name="venue" class="form-control" value="{{ old('venue',$event->venue) }}">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Seats</label>
          <input aria-label="Seats" type="number" name="seats" class="form-control" value="{{ old('seats',$event->seats) }}" min="1">
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Registration Deadline</label>
          <input aria-label="Registration Deadline" type="date" name="registration_deadline" class="form-control" value="{{ old('registration_deadline',$event->registration_deadline?->format('Y-m-d')) }}">
        </div>
        <div class="col-md-6 d-flex align-items-end">
          <div class="form-check mb-2">
            <input type="checkbox" name="is_published" value="1" class="form-check-input" id="isPublished"
                {{ old('is_published',$event->is_published) ? 'checked' : '' }}>
            <label class="form-check-label small" for="isPublished">Published</label>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Description</label>
          <textarea aria-label="Description" name="description" class="form-control" rows="3">{{ old('description',$event->description) }}</textarea>
        </div>
        <div class="col-12 d-flex gap-2 pt-2">
          <button type="submit" class="btn btn-primary">Update Event</button>
          <a href="{{ route('cmc.events') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
</div></div>
@endsection
