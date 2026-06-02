@extends('layouts.admin')
@section('title','Enroll Student')
@section('page-title','Enroll Student in Subjects')
@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Individual Enrollment</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.enrollments.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Student *</label>
                        <select name="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                            <option value="">Select student</option>
                            @foreach($students as $s)<option value="{{ $s->id }}" @selected(old('student_id')==$s->id)>{{ $s->user->name }} ({{ $s->enrollment_number }}) – {{ $s->course->code }}</option>@endforeach
                        </select>
                        @error('student_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Semester *</label>
                        <select name="semester_id" class="form-select" required>
                            <option value="">Select semester</option>
                            @foreach($semesters as $s)<option value="{{ $s->id }}" @selected(old('semester_id')==$s->id)>{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subjects * <span class="text-muted small">(select multiple)</span></label>
                        <div class="border rounded p-2" style="max-height:250px;overflow-y:auto">
                            @foreach($subjects as $s)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="subject_ids[]" value="{{ $s->id }}" id="sub_{{ $s->id }}" @checked(in_array($s->id, old('subject_ids', [])))>
                                <label class="form-check-label small" for="sub_{{ $s->id }}">
                                    <span class="fw-semibold">{{ $s->name }}</span> <span class="text-muted">({{ $s->code }}, {{ $s->credits }} cr)</span>
                                </label>
                            </div>
                            @endforeach
                        </div>
                        @error('subject_ids')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Enroll</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Bulk Enrollment (Entire Course)</div>
            <div class="card-body">
                <p class="text-muted small">Enroll all active students of a course into selected subjects at once.</p>
                <form method="POST" action="{{ route('admin.enrollments.bulk') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Course *</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">Select course</option>
                            @foreach($students->pluck('course')->unique('id') as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Semester *</label>
                        <select name="semester_id" class="form-select" required>
                            <option value="">Select semester</option>
                            @foreach($semesters as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subjects *</label>
                        <div class="border rounded p-2" style="max-height:200px;overflow-y:auto">
                            @foreach($subjects as $s)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="subject_ids[]" value="{{ $s->id }}" id="bsub_{{ $s->id }}">
                                <label class="form-check-label small" for="bsub_{{ $s->id }}">{{ $s->name }} ({{ $s->code }})</label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <button type="submit" class="btn btn-warning text-dark" onclick="return confirm('Enroll ALL students of selected course?')">Bulk Enroll</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
