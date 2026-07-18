@extends('layouts.admin')
@section('title', 'Edit Exam')
@section('page-title', 'Edit Exam')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.exams.index') }}">Exams</a></li>
    <li class="breadcrumb-item active">Edit Exam</li>
@endsection

@section('content')
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card" style="max-width:700px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Exam — {{ $exam->name }}</span>
        <a href="{{ route('admin.exams.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        @if($exam->published_at)
            <div class="alert alert-info">
                <strong>Published exam locked.</strong> Official exam details cannot be edited after result publication.
            </div>
        @endif
        <form method="POST" action="{{ route('admin.exams.update', $exam) }}">
            @csrf @method('PUT')
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label class="form-label">Exam Name <span class="text-danger">*</span></label>
                    <input aria-label="Name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $exam->name) }}" required>
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Type <span class="text-danger">*</span></label>
                    <select aria-label="Type" name="type" class="form-select" required>
                        @foreach(['internal','midterm','final','practical','assignment'] as $t)
                            <option value="{{ $t }}" @selected(old('type',$exam->type)==$t)>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Semester <span class="text-danger">*</span></label>
                    <select aria-label="Semester" name="semester_id" class="form-select @error('semester_id') is-invalid @enderror" required>
                        @foreach($semesters as $s)<option value="{{ $s->id }}" @selected($s->id==old('semester_id',$exam->semester_id))>{{ $s->name }}</option>@endforeach
                    </select>
                    @error('semester_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Subject <span class="text-danger">*</span></label>
                    <select aria-label="Subject" name="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required>
                        @foreach($subjects as $s)<option value="{{ $s->id }}" @selected($s->id==old('subject_id',$exam->subject_id))>{{ $s->name }}</option>@endforeach
                    </select>
                    @error('subject_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Exam Date <span class="text-danger">*</span></label>
                    <input aria-label="Exam Date" type="date" name="exam_date" class="form-control" value="{{ old('exam_date', $exam->exam_date->format('Y-m-d')) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Total Marks <span class="text-danger">*</span></label>
                    <input aria-label="Total Marks" type="number" name="total_marks" class="form-control" value="{{ old('total_marks', $exam->total_marks) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Passing Marks <span class="text-danger">*</span></label>
                    <input aria-label="Passing Marks" type="number" name="passing_marks" class="form-control" value="{{ old('passing_marks', $exam->passing_marks) }}" required>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary" @disabled($exam->published_at)><i class="bi bi-check-circle me-1"></i>Update Exam</button>
                <a href="{{ route('admin.exams.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
