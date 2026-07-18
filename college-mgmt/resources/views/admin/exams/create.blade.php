@extends('layouts.admin')
@section('title', 'Schedule Exam')
@section('page-title', 'Schedule Exam')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.exams.index') }}">Exams</a></li>
    <li class="breadcrumb-item active">Add Exam</li>
@endsection

@section('content')

<div class="card" style="max-width:700px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-clipboard-plus me-2 text-primary"></i>Schedule New Exam</span>
        <a href="{{ route('admin.exams.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.exams.store') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label class="form-label">Exam Name <span class="text-danger">*</span></label>
                    <input aria-label="Exam name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. Mid Term Exam 1" required>
                    <div class="form-text">Descriptive name for this exam.</div>
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Type <span class="text-danger">*</span></label>
                    <select aria-label="Type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                        @foreach(['internal','midterm','final','practical','assignment'] as $t)
                            <option value="{{ $t }}" @selected(old('type')==$t)>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Exam category.</div>
                    @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Semester <span class="text-danger">*</span></label>
                    <select aria-label="Semester" name="semester_id" class="form-select @error('semester_id') is-invalid @enderror" required>
                        <option value="">Select semester…</option>
                        @foreach($semesters as $s)<option value="{{ $s->id }}" @selected(old('semester_id')==$s->id)>{{ $s->name }}</option>@endforeach
                    </select>
                    @error('semester_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Subject <span class="text-danger">*</span></label>
                    <select aria-label="Subject" name="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required>
                        <option value="">Select subject…</option>
                        @foreach($subjects as $s)<option value="{{ $s->id }}" @selected(old('subject_id')==$s->id)>{{ $s->name }} ({{ $s->code }})</option>@endforeach
                    </select>
                    @error('subject_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Exam Date <span class="text-danger">*</span></label>
                    <input aria-label="Exam Date" type="date" name="exam_date" class="form-control @error('exam_date') is-invalid @enderror" value="{{ old('exam_date') }}" required>
                    @error('exam_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Start Time</label>
                    <input aria-label="Start Time" type="time" name="start_time" class="form-control" value="{{ old('start_time') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Time</label>
                    <input aria-label="End Time" type="time" name="end_time" class="form-control" value="{{ old('end_time') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Total Marks <span class="text-danger">*</span></label>
                    <input aria-label="Total Marks" type="number" name="total_marks" class="form-control" value="{{ old('total_marks', 100) }}" min="1" required>
                    <div class="form-text">Maximum marks for this exam.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Passing Marks <span class="text-danger">*</span></label>
                    <input aria-label="Passing Marks" type="number" name="passing_marks" class="form-control" value="{{ old('passing_marks', 35) }}" min="1" required>
                    <div class="form-text">Minimum marks to pass.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Classroom</label>
                    <select aria-label="Classroom" name="classroom_id" class="form-select">
                        <option value="">— None —</option>
                        @foreach($classrooms as $r)<option value="{{ $r->id }}" @selected(old('classroom_id')==$r->id)>{{ $r->name }} ({{ $r->room_number }})</option>@endforeach
                    </select>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Schedule Exam</button>
                <a href="{{ route('admin.exams.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
