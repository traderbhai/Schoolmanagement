@extends('layouts.admin')
@section('title', 'Add Timetable Entry')
@section('page-title', 'Add Timetable Entry')
@section('content')

<div class="card" style="max-width:720px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>New Timetable Entry</span>
        <a href="{{ route('admin.timetable.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.timetable.store') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Semester <span class="text-danger">*</span></label>
                    <select aria-label="Semester" name="semester_id" class="form-select @error('semester_id') is-invalid @enderror" required>
                        <option value="">Select semester…</option>
                        @foreach($semesters as $s)
                            <option value="{{ $s->id }}" @selected(old('semester_id')==$s->id)>{{ $s->name }} ({{ $s->academicYear->name }})</option>
                        @endforeach
                    </select>
                    @error('semester_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Course <span class="text-danger">*</span></label>
                    <select aria-label="Course" name="course_id" class="form-select @error('course_id') is-invalid @enderror" required>
                        <option value="">Select course…</option>
                        @foreach($courses as $c)
                            <option value="{{ $c->id }}" @selected(old('course_id')==$c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                    @error('course_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Subject <span class="text-danger">*</span></label>
                    <select aria-label="Subject" name="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required>
                        <option value="">Select subject…</option>
                        @foreach($subjects as $s)
                            <option value="{{ $s->id }}" @selected(old('subject_id')==$s->id)>{{ $s->name }} ({{ $s->code }})</option>
                        @endforeach
                    </select>
                    @error('subject_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Teacher <span class="text-danger">*</span></label>
                    <select aria-label="Teacher" name="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror" required>
                        <option value="">Select teacher…</option>
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}" @selected(old('teacher_id')==$t->id)>{{ $t->user->name }} ({{ $t->designation }})</option>
                        @endforeach
                    </select>
                    @error('teacher_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Classroom <span class="text-danger">*</span></label>
                    <select aria-label="Classroom" name="classroom_id" class="form-select @error('classroom_id') is-invalid @enderror" required>
                        <option value="">Select room…</option>
                        @foreach($classrooms as $r)
                            <option value="{{ $r->id }}" @selected(old('classroom_id')==$r->id)>{{ $r->name }} ({{ $r->room_number }}, cap: {{ $r->capacity }})</option>
                        @endforeach
                    </select>
                    @error('classroom_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Time Slot <span class="text-danger">*</span></label>
                    <select aria-label="Timetable Slot" name="timetable_slot_id" class="form-select @error('timetable_slot_id') is-invalid @enderror" required>
                        <option value="">Select slot…</option>
                        @foreach($slots as $sl)
                            <option value="{{ $sl->id }}" @selected(old('timetable_slot_id')==$sl->id)>{{ $sl->name }} ({{ $sl->start_time }}–{{ $sl->end_time }})</option>
                        @endforeach
                    </select>
                    @error('timetable_slot_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Day of Week <span class="text-danger">*</span></label>
                    <select aria-label="Day Of Week" name="day_of_week" class="form-select @error('day_of_week') is-invalid @enderror" required>
                        <option value="">Select day…</option>
                        @foreach([1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday'] as $v=>$d)
                            <option value="{{ $v }}" @selected(old('day_of_week')==$v)>{{ $d }}</option>
                        @endforeach
                    </select>
                    @error('day_of_week')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Entry</button>
                <a href="{{ route('admin.timetable.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
