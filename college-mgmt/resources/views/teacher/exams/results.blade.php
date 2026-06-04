@extends('layouts.teacher')
@section('title','Enter Results — '.$exam->name)
@section('page-title','Enter Exam Results')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('teacher.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('teacher.exams.index') }}">Exams</a></li>
    <li class="breadcrumb-item active">{{ $exam->name }}</li>
@endsection

@section('content')
{{-- Exam Info Header --}}
<div class="card mb-3" style="border-left:4px solid #6366f1;">
    <div class="card-body py-2">
        <div class="row align-items-center">
            <div class="col">
                <span class="fw-bold fs-6 text-primary">{{ $exam->name }}</span>
                <span class="text-muted mx-2">·</span>
                <span class="text-muted">{{ $exam->subject->name ?? '—' }}</span>
                <span class="text-muted mx-2">·</span>
                <span class="badge bg-secondary">{{ ucfirst($exam->type) }}</span>
                <span class="text-muted mx-2">·</span>
                <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>{{ $exam->exam_date ? $exam->exam_date->format('d M Y') : '—' }}</span>
            </div>
            <div class="col-auto">
                <span class="badge bg-light text-dark border fs-6">
                    Max: <strong>{{ $exam->total_marks }}</strong> &nbsp;|&nbsp; Pass: <strong>{{ $exam->passing_marks }}</strong>
                </span>
            </div>
        </div>
    </div>
</div>

@if($students->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-people display-4 d-block mb-3"></i>
            <h6>No students enrolled in this subject.</h6>
        </div>
    </div>
@else
<form method="POST" action="{{ route('teacher.exams.results.save', $exam) }}">
    @csrf
    <div class="card">
        <div class="card-header">
            <i class="bi bi-table me-2 text-primary"></i>
            Enter marks for <strong>{{ $students->count() }}</strong> students
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:50px">#</th>
                            <th>Roll No.</th>
                            <th>Student Name</th>
                            <th>Enrollment No.</th>
                            <th class="text-center" style="width:90px">Absent</th>
                            <th style="width:160px">Marks (/ {{ $exam->total_marks }})</th>
                            <th style="width:200px">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($students as $s)
                    @php $existingResult = $s->examResults->first(); @endphp
                    <tr>
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td><code>{{ $s->roll_number }}</code></td>
                        <td class="fw-semibold">{{ $s->user->name }}</td>
                        <td><span class="text-muted small">{{ $s->enrollment_number }}</span></td>
                        <td class="text-center">
                            <div class="form-check d-inline-block">
                                <input type="checkbox"
                                    name="results[{{ $s->id }}][is_absent]"
                                    value="1"
                                    class="form-check-input absent-cb"
                                    data-student="{{ $s->id }}"
                                    @checked($existingResult && $existingResult->is_absent)>
                            </div>
                        </td>
                        <td>
                            <input type="number"
                                name="results[{{ $s->id }}][marks_obtained]"
                                class="form-control form-control-sm marks-input marks-input-{{ $s->id }}"
                                min="0"
                                max="{{ $exam->total_marks }}"
                                step="0.5"
                                placeholder="0–{{ $exam->total_marks }}"
                                value="{{ $existingResult && !$existingResult->is_absent ? $existingResult->marks_obtained : '' }}"
                                @disabled($existingResult && $existingResult->is_absent)>
                        </td>
                        <td>
                            <input type="text"
                                name="results[{{ $s->id }}][remarks]"
                                class="form-control form-control-sm"
                                placeholder="Optional"
                                value="{{ $existingResult ? $existingResult->remarks : '' }}">
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-save me-2"></i>Save All Results
            </button>
            <a href="{{ route('teacher.exams.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Exams
            </a>
        </div>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
document.querySelectorAll('.absent-cb').forEach(cb => {
    cb.addEventListener('change', function() {
        const sid = this.dataset.student;
        const input = document.querySelector(`.marks-input-${sid}`);
        if (input) {
            input.disabled = this.checked;
            if (this.checked) input.value = '';
        }
    });
});
</script>
@endpush
