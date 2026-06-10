@extends('layouts.admin')

@section('title', 'Create Exam')

@section('page-title', 'Create Exam')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Schedule New Exam</h5>
    <a href="{{ route('exam-cell.exams.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Exams
    </a>
</div>

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show">
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('exam-cell.exams.store') }}">
            @csrf

            <div class="row g-3">
                {{-- Exam Name --}}
                <div class="col-md-6">
                    <label for="name" class="form-label fw-semibold">Exam Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name"
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" placeholder="e.g. Mid Semester Examination" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Exam Type --}}
                <div class="col-md-6">
                    <label for="type" class="form-label fw-semibold">Exam Type <span class="text-danger">*</span></label>
                    <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
                        <option value="">— Select Type —</option>
                        @foreach(['internal'=>'Internal','midterm'=>'Mid Term','endterm'=>'End Term','practical'=>'Practical','assignment'=>'Assignment','quiz'=>'Quiz'] as $val => $label)
                        <option value="{{ $val }}" {{ old('type') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Program --}}
                <div class="col-md-6">
                    <label for="program_id" class="form-label fw-semibold">Program <span class="text-danger">*</span></label>
                    <select name="program_id" id="program_id" class="form-select @error('program_id') is-invalid @enderror" required>
                        <option value="">— Select Program —</option>
                        @foreach($programs as $program)
                        <option value="{{ $program->id }}" {{ old('program_id') == $program->id ? 'selected' : '' }}>
                            {{ $program->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('program_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Subject --}}
                <div class="col-md-6">
                    <label for="subject_id" class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                    <select name="subject_id" id="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required>
                        <option value="">— Select Subject —</option>
                        @foreach($subjects as $subject)
                        <option value="{{ $subject->id }}" {{ old('subject_id') == $subject->id ? 'selected' : '' }}>
                            {{ $subject->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Term --}}
                <div class="col-md-6">
                    <label for="term_id" class="form-label fw-semibold">Term</label>
                    <select name="term_id" id="term_id" class="form-select @error('term_id') is-invalid @enderror">
                        <option value="">— None —</option>
                        @foreach($terms as $term)
                        <option value="{{ $term->id }}" {{ old('term_id') == $term->id ? 'selected' : '' }}>
                            {{ $term->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('term_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Exam Date --}}
                <div class="col-md-6">
                    <label for="exam_date" class="form-label fw-semibold">Exam Date <span class="text-danger">*</span></label>
                    <input type="date" name="exam_date" id="exam_date"
                           class="form-control @error('exam_date') is-invalid @enderror"
                           value="{{ old('exam_date') }}" required>
                    @error('exam_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Start Time --}}
                <div class="col-md-3">
                    <label for="start_time" class="form-label fw-semibold">Start Time</label>
                    <input type="time" name="start_time" id="start_time"
                           class="form-control @error('start_time') is-invalid @enderror"
                           value="{{ old('start_time') }}">
                    @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- End Time --}}
                <div class="col-md-3">
                    <label for="end_time" class="form-label fw-semibold">End Time</label>
                    <input type="time" name="end_time" id="end_time"
                           class="form-control @error('end_time') is-invalid @enderror"
                           value="{{ old('end_time') }}">
                    @error('end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Total Marks --}}
                <div class="col-md-3">
                    <label for="total_marks" class="form-label fw-semibold">Total Marks <span class="text-danger">*</span></label>
                    <input type="number" name="total_marks" id="total_marks" min="1"
                           class="form-control @error('total_marks') is-invalid @enderror"
                           value="{{ old('total_marks', 100) }}" required>
                    @error('total_marks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Passing Marks --}}
                <div class="col-md-3">
                    <label for="passing_marks" class="form-label fw-semibold">Passing Marks <span class="text-danger">*</span></label>
                    <input type="number" name="passing_marks" id="passing_marks" min="1"
                           class="form-control @error('passing_marks') is-invalid @enderror"
                           value="{{ old('passing_marks', 40) }}" required>
                    @error('passing_marks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Classroom --}}
                <div class="col-md-6">
                    <label for="classroom_id" class="form-label fw-semibold">Classroom / Venue</label>
                    <select name="classroom_id" id="classroom_id" class="form-select @error('classroom_id') is-invalid @enderror">
                        <option value="">— None —</option>
                        @foreach($classrooms as $room)
                        <option value="{{ $room->id }}" {{ old('classroom_id') == $room->id ? 'selected' : '' }}>
                            {{ $room->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('classroom_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <hr class="my-4">
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('exam-cell.exams.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-calendar-plus me-1"></i>Schedule Exam
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
