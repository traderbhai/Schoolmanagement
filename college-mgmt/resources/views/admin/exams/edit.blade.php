@extends('layouts.admin')
@section('title','Edit Exam')
@section('page-title','Edit Exam')
@section('content')
<div class="card" style="max-width:680px">
    <div class="card-header">Edit: {{ $exam->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.exams.update', $exam) }}">
            @csrf @method('PUT')
            <div class="row g-3 mb-3">
                <div class="col-md-8"><label class="form-label fw-semibold">Exam Name *</label><input type="text" name="name" class="form-control" value="{{ old('name', $exam->name) }}" required></div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Type *</label>
                    <select name="type" class="form-select" required>
                        @foreach(['internal','midterm','final','practical','assignment'] as $t)<option value="{{ $t }}" @selected(old('type',$exam->type)==$t)>{{ ucfirst($t) }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Semester *</label>
                    <select name="semester_id" class="form-select" required>
                        @foreach($semesters as $s)<option value="{{ $s->id }}" @selected($s->id==old('semester_id',$exam->semester_id))>{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Subject *</label>
                    <select name="subject_id" class="form-select" required>
                        @foreach($subjects as $s)<option value="{{ $s->id }}" @selected($s->id==old('subject_id',$exam->subject_id))>{{ $s->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label fw-semibold">Date *</label><input type="date" name="exam_date" class="form-control" value="{{ old('exam_date', $exam->exam_date->format('Y-m-d')) }}" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Total Marks</label><input type="number" name="total_marks" class="form-control" value="{{ old('total_marks', $exam->total_marks) }}" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Passing Marks</label><input type="number" name="passing_marks" class="form-control" value="{{ old('passing_marks', $exam->passing_marks) }}" required></div>
            </div>
            <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">Update</button><a href="{{ route('admin.exams.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
