@extends('layouts.admin')
@section('title', 'Edit Timetable Entry')
@section('page-title', 'Edit Timetable Entry')
@section('content')

<div class="card" style="max-width:720px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Timetable Entry</span>
        <a href="{{ route('admin.timetable.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.timetable.update', $entry) }}">
            @csrf @method('PUT')
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Semester <span class="text-danger">*</span></label>
                    <select aria-label="Semester" name="semester_id" class="form-select" required>
                        @foreach($semesters as $s)<option value="{{ $s->id }}" @selected($s->id==$entry->semester_id)>{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Course <span class="text-danger">*</span></label>
                    <select aria-label="Course" name="course_id" class="form-select" required>
                        @foreach($courses as $c)<option value="{{ $c->id }}" @selected($c->id==$entry->course_id)>{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Subject <span class="text-danger">*</span></label>
                    <select aria-label="Subject" name="subject_id" class="form-select" required>
                        @foreach($subjects as $s)<option value="{{ $s->id }}" @selected($s->id==$entry->subject_id)>{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Teacher <span class="text-danger">*</span></label>
                    <select aria-label="Teacher" name="teacher_id" class="form-select" required>
                        @foreach($teachers as $t)<option value="{{ $t->id }}" @selected($t->id==$entry->teacher_id)>{{ $t->user->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Classroom <span class="text-danger">*</span></label>
                    <select aria-label="Classroom" name="classroom_id" class="form-select" required>
                        @foreach($classrooms as $r)<option value="{{ $r->id }}" @selected($r->id==$entry->classroom_id)>{{ $r->name }} ({{ $r->room_number }})</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Time Slot <span class="text-danger">*</span></label>
                    <select aria-label="Timetable Slot" name="timetable_slot_id" class="form-select" required>
                        @foreach($slots as $sl)<option value="{{ $sl->id }}" @selected($sl->id==$entry->timetable_slot_id)>{{ $sl->name }} ({{ $sl->start_time }}–{{ $sl->end_time }})</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Day of Week <span class="text-danger">*</span></label>
                    <select aria-label="Day Of Week" name="day_of_week" class="form-select" required>
                        @foreach([1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday'] as $v=>$d)
                            <option value="{{ $v }}" @selected($v==$entry->day_of_week)>{{ $d }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Update Entry</button>
                <a href="{{ route('admin.timetable.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
