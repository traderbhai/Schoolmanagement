@extends('layouts.admin')
@section('title','Edit Exam')
@section('page-title','Edit Exam')
@section('content')
<div class="row justify-content-center"><div class="col-lg-8">
<div class="card">
  <div class="card-header bg-transparent fw-semibold">Edit Exam</div>
  <div class="card-body">
    @if(session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($exam->published_at)
      <div class="alert alert-info">
        <strong>Published exam locked.</strong> Official exam details cannot be edited after result publication.
      </div>
    @endif
    <form method="POST" action="{{ route('exam-cell.exams.update', $exam) }}">
      @csrf @method('PUT')
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label small fw-semibold">Exam Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" value="{{ old('name',$exam->name) }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Type <span class="text-danger">*</span></label>
          <select name="type" class="form-select" required>
            @foreach(['internal','midterm','endterm','practical','assignment','quiz'] as $t)
            <option value="{{ $t }}" {{ old('type',$exam->type)===$t?'selected':'' }}>{{ ucfirst($t) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Program <span class="text-danger">*</span></label>
          <select name="program_id" class="form-select" required>
            <option value="">— Select —</option>
            @foreach($programs as $p)
            <option value="{{ $p->id }}" {{ old('program_id',$exam->program_id)==$p->id?'selected':'' }}>{{ $p->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Subject <span class="text-danger">*</span></label>
          <select name="subject_id" class="form-select" required>
            <option value="">— Select —</option>
            @foreach($subjects as $s)
            <option value="{{ $s->id }}" {{ old('subject_id',$exam->subject_id)==$s->id?'selected':'' }}>{{ $s->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Term</label>
          <select name="term_id" class="form-select">
            <option value="">— None —</option>
            @foreach($terms as $t)
            <option value="{{ $t->id }}" {{ old('term_id',$exam->term_id)==$t->id?'selected':'' }}>{{ $t->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Classroom</label>
          <select name="classroom_id" class="form-select">
            <option value="">— None —</option>
            @foreach($classrooms as $c)
            <option value="{{ $c->id }}" {{ old('classroom_id',$exam->classroom_id)==$c->id?'selected':'' }}>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Exam Date <span class="text-danger">*</span></label>
          <input type="date" name="exam_date" class="form-control" value="{{ old('exam_date',$exam->exam_date?->format('Y-m-d')) }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Start Time</label>
          <input type="time" name="start_time" class="form-control" value="{{ old('start_time',$exam->start_time) }}">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">End Time</label>
          <input type="time" name="end_time" class="form-control" value="{{ old('end_time',$exam->end_time) }}">
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Total Marks <span class="text-danger">*</span></label>
          <input type="number" name="total_marks" class="form-control" value="{{ old('total_marks',$exam->total_marks) }}" min="1" required>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Passing Marks</label>
          <input type="number" name="passing_marks" class="form-control" value="{{ old('passing_marks',$exam->passing_marks) }}" min="0">
        </div>
        <div class="col-12 d-flex gap-2 pt-2">
          <button type="submit" class="btn btn-primary" @disabled($exam->published_at)>Update Exam</button>
          <a href="{{ route('exam-cell.exams') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
</div></div>
@endsection
