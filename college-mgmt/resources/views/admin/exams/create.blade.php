@extends('layouts.admin')
@section('title','Schedule Exam')
@section('page-title','Schedule Exam')
@section('content')
<div class="card" style="max-width:680px">
    <div class="card-header">New Exam</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.exams.store') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-8"><label class="form-label fw-semibold">Exam Name *</label><input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required placeholder="e.g. Mid Term Exam 1">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Type *</label>
                    <select name="type" class="form-select" required>
                        @foreach(['internal','midterm','final','practical','assignment'] as $t)<option value="{{ $t }}" @selected(old('type')==$t)>{{ ucfirst($t) }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Semester *</label>
                    <select name="semester_id" class="form-select" required>
                        <option value="">Select semester</option>
                        @foreach($semesters as $s)<option value="{{ $s->id }}" @selected(old('semester_id')==$s->id)>{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Subject *</label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">Select subject</option>
                        @foreach($subjects as $s)<option value="{{ $s->id }}" @selected(old('subject_id')==$s->id)>{{ $s->name }} ({{ $s->code }})</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label fw-semibold">Exam Date *</label><input type="date" name="exam_date" class="form-control" value="{{ old('exam_date') }}" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Start Time</label><input type="time" name="start_time" class="form-control" value="{{ old('start_time') }}"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">End Time</label><input type="time" name="end_time" class="form-control" value="{{ old('end_time') }}"></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label fw-semibold">Total Marks *</label><input type="number" name="total_marks" class="form-control" value="{{ old('total_marks', 100) }}" min="1" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Passing Marks *</label><input type="number" name="passing_marks" class="form-control" value="{{ old('passing_marks', 35) }}" min="1" required></div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Classroom</label>
                    <select name="classroom_id" class="form-select">
                        <option value="">-- None --</option>
                        @foreach($classrooms as $r)<option value="{{ $r->id }}" @selected(old('classroom_id')==$r->id)>{{ $r->name }} ({{ $r->room_number }})</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">Schedule</button><a href="{{ route('admin.exams.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
