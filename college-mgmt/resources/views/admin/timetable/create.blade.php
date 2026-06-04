@extends('layouts.admin')
@section('title', 'Add Timetable Entry')
@section('page-title', 'Add Timetable Entry')
@section('content')
<div class="card" style="max-width:700px">
    <div class="card-header">New Timetable Slot Assignment</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.timetable.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Semester *</label>
                    <select name="semester_id" class="form-select @error('semester_id') is-invalid @enderror" required>
                        <option value="">Select semester</option>
                        @foreach($semesters as $s)
                        <option value="{{ $s->id }}" @selected(old('semester_id')==$s->id)>{{ $s->name }} ({{ $s->academicYear->name }})</option>
                        @endforeach
                    </select>
                    @error('semester_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Course *</label>
                    <select name="course_id" class="form-select" required>
                        <option value="">Select course</option>
                        @foreach($courses as $c)
                        <option value="{{ $c->id }}" @selected(old('course_id')==$c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Subject *</label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">Select subject</option>
                        @foreach($subjects as $s)
                        <option value="{{ $s->id }}" @selected(old('subject_id')==$s->id)>{{ $s->name }} ({{ $s->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Teacher *</label>
                    <select name="teacher_id" class="form-select" required>
                        <option value="">Select teacher</option>
                        @foreach($teachers as $t)
                        <option value="{{ $t->id }}" @selected(old('teacher_id')==$t->id)>{{ $t->user->name }} ({{ $t->designation }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Classroom *</label>
                    <select name="classroom_id" class="form-select" required>
                        <option value="">Select room</option>
                        @foreach($classrooms as $r)
                        <option value="{{ $r->id }}" @selected(old('classroom_id')==$r->id)>{{ $r->name }} ({{ $r->room_number }}, cap: {{ $r->capacity }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Time Slot *</label>
                    <select name="timetable_slot_id" class="form-select" required>
                        <option value="">Select slot</option>
                        @foreach($slots as $sl)
                        <option value="{{ $sl->id }}" @selected(old('timetable_slot_id')==$sl->id)>{{ $sl->name }} ({{ $sl->start_time }}–{{ $sl->end_time }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Day of Week *</label>
                    <select name="day_of_week" class="form-select" required>
                        <option value="">Select day</option>
                        @foreach([1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday'] as $v=>$d)
                        <option value="{{ $v }}" @selected(old('day_of_week')==$v)>{{ $d }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-success">Save Entry</button>
                <a href="{{ route('admin.timetable.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
