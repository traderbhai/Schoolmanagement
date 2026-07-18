@extends('layouts.admin')

@section('title', 'Edit Calendar Event')
@section('page-title', 'Edit Calendar Event')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('academic.academic-calendars.update', $academicCalendar) }}" method="POST">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Event Name</label>
                        <input aria-label="Event Name" type="text" name="event_name" class="form-control" value="{{ old('event_name', $academicCalendar->event_name) }}" required>
                        @error('event_name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Event Date</label>
                        <input aria-label="Event Date" type="date" name="event_date" class="form-control" value="{{ old('event_date', $academicCalendar->event_date->format('Y-m-d')) }}" required>
                        @error('event_date')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Event Type</label>
                        <select aria-label="Event Type" name="event_type" class="form-select" required>
                            @foreach(['holiday','exam_week','semester_start','semester_end','class_start','class_end','other'] as $type)
                                <option value="{{ $type }}" {{ old('event_type',$academicCalendar->event_type)==$type?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$type)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Description</label>
                        <textarea aria-label="Description" name="description" class="form-control" rows="2">{{ old('description', $academicCalendar->description) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" name="is_holiday" value="1" class="form-check-input" id="is_holiday" {{ old('is_holiday', $academicCalendar->is_holiday)?'checked':'' }}>
                            <label class="form-check-label" for="is_holiday">Mark as Holiday</label>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Update Event</button>
                    <a href="{{ route('academic.academic-calendars.show', $academicCalendar) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
