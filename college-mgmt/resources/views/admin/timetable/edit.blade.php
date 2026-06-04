@extends('layouts.admin')
@section('title', 'Edit Timetable Entry')
@section('page-title', 'Edit Timetable Entry')
@section('content')
<div class="card" style="max-width:700px">
    <div class="card-header">Edit Timetable Entry</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.timetable.update', $entry) }}">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Semester *</label>
                    <select name="semester_id" class="form-select" required>@foreach($semesters as $s)<option value="{{ $s->id }}" @selected($s->id==$entry->semester_id)>{{ $s->name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Course *</label>
                    <select name="course_id" class="form-select" required>@foreach($courses as $c)<option value="{{ $c->id }}" @selected($c->id==$entry->course_id)>{{ $c->name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Subject *</label>
                    <select name="subject_id" class="form-select" required>@foreach($subjects as $s)<option value="{{ $s->id }}" @selected($s->id==$entry->subject_id)>{{ $s->name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Teacher *</label>
                    <select name="teacher_id" class="form-select" required>@foreach($teachers as $t)<option value="{{ $t->id }}" @selected($t->id==$entry->teacher_id)>{{ $t->user->name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Classroom *</label>
                    <select name="classroom_id" class="form-select" required>@foreach($classrooms as $r)<option value="{{ $r->id }}" @selected($r->id==$entry->classroom_id)>{{ $r->name }} ({{ $r->room_number }})</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Time Slot *</label>
                    <select name="timetable_slot_id" class="form-select" required>@foreach($slots as $sl)<option value="{{ $sl->id }}" @selected($sl->id==$entry->timetable_slot_id)>{{ $sl->name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Day *</label>
                    <select name="day_of_week" class="form-select" required>@foreach([1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday'] as $v=>$d)<option value="{{ $v }}" @selected($v==$entry->day_of_week)>{{ $d }}</option>@endforeach</select></div>
            </div>
            <div class="mt-4 d-flex gap-2"><button type="submit" class="btn btn-primary">Update</button><a href="{{ route('admin.timetable.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
