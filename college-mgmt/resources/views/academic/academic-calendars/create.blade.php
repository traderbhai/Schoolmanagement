@extends('layouts.admin')

@section('title', 'Create Calendar Event')
@section('page-title', 'Create Calendar Event')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('academic.academic-calendars.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Term</label>
                        <select aria-label="Term" name="term_id" class="form-select" required>
                            <option value="">Select Term</option>
                            @foreach($terms as $term)
                                <option value="{{ $term->id }}" {{ old('term_id')==$term->id?'selected':'' }}>{{ $term->name }}</option>
                            @endforeach
                        </select>
                        @error('term_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Event Name</label>
                        <input aria-label="Event Name" type="text" name="event_name" class="form-control" value="{{ old('event_name') }}" required>
                        @error('event_name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Event Date</label>
                        <input aria-label="Event Date" type="date" name="event_date" class="form-control" value="{{ old('event_date') }}" required>
                        @error('event_date')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Event Type</label>
                        <select aria-label="Event Type" name="event_type" class="form-select" required>
                            <option value="holiday" {{ old('event_type')=='holiday'?'selected':'' }}>Holiday</option>
                            <option value="exam_week" {{ old('event_type')=='exam_week'?'selected':'' }}>Exam Week</option>
                            <option value="semester_start" {{ old('event_type')=='semester_start'?'selected':'' }}>Semester Start</option>
                            <option value="semester_end" {{ old('event_type')=='semester_end'?'selected':'' }}>Semester End</option>
                            <option value="class_start" {{ old('event_type')=='class_start'?'selected':'' }}>Class Start</option>
                            <option value="class_end" {{ old('event_type')=='class_end'?'selected':'' }}>Class End</option>
                            <option value="other" {{ old('event_type')=='other'?'selected':'' }}>Other</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Description</label>
                        <textarea aria-label="Description" name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" name="is_holiday" value="1" class="form-check-input" id="is_holiday" {{ old('is_holiday')?'checked':'' }}>
                            <label class="form-check-label" for="is_holiday">Mark as Holiday</label>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Create Event</button>
                    <a href="{{ route('academic.academic-calendars.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
