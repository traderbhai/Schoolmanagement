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
<div class="card mb-4 border-0 border-start border-4 border-primary" style="box-shadow:var(--shadow-sm)">
    <div class="card-body py-3">
        <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <span class="fw-bold fs-6 text-primary">{{ $exam->name }}</span>
                <span class="text-muted">·</span>
                <span class="text-muted">{{ $exam->subject->name ?? '—' }}</span>
                <span class="badge bg-secondary">{{ ucfirst($exam->type) }}</span>
                <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>{{ $exam->exam_date ? $exam->exam_date->format('d M Y') : '—' }}</span>
            </div>
            <div>
                <span class="badge bg-light text-dark border fs-6 fw-semibold px-3 py-2">
                    Max: <strong>{{ $exam->total_marks }}</strong> &nbsp;|&nbsp; Pass: <strong>{{ $exam->passing_marks }}</strong>
                </span>
            </div>
        </div>
    </div>
</div>

@if($students->isEmpty())
    <div class="card" style="box-shadow:var(--shadow-sm)">
        <div class="card-body">
            <div class="empty-state py-5">
                <div class="empty-icon"><i class="bi bi-people" style="font-size:3rem;color:var(--clr-text-muted)"></i></div>
                <h6 class="mt-3 text-muted">No Students Enrolled</h6>
                <p class="text-muted small mb-0">No students enrolled in this subject for the exam semester.</p>
            </div>
        </div>
    </div>
@else
<form method="POST" action="{{ route('teacher.exams.results.save', $exam) }}">
    @csrf
    <div class="card" style="box-shadow:var(--shadow-sm)">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-table text-primary"></i>
            <span class="fw-semibold">Enter marks for <strong>{{ $students->count() }}</strong> students</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="resultsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px">#</th>
                            <th style="width:90px">Roll No.</th>
                            <th>Student</th>
                            <th style="width:110px" class="text-center">Absent</th>
                            <th style="width:180px">Marks <span class="text-muted fw-normal">/ {{ $exam->total_marks }}</span></th>
                            <th style="width:180px">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($students as $s)
                    @php $existingResult = $s->examResults->first(); @endphp
                    <tr class="result-row" data-student="{{ $s->id }}">
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td><code class="small">{{ $s->roll_number }}</code></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
                                     style="width:32px;height:32px;background:var(--clr-primary,#4f46e5);font-size:.8rem">
                                    {{ strtoupper(substr($s->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="fw-semibold" style="font-size:.88rem">{{ $s->user->name }}</div>
                                    <div class="text-muted" style="font-size:.73rem">{{ $s->enrollment_number }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="form-check d-flex justify-content-center mb-0">
                                <input type="checkbox"
                                       name="results[{{ $s->id }}][is_absent]"
                                       value="1"
                                       class="form-check-input absent-cb"
                                       data-student="{{ $s->id }}"
                                       id="absent_{{ $s->id }}"
                                       @checked($existingResult && $existingResult->is_absent)>
                                <label class="form-check-label ms-1 text-danger small fw-semibold" for="absent_{{ $s->id }}">Absent</label>
                            </div>
                        </td>
                        <td>
                            <input type="number"
                                   name="results[{{ $s->id }}][marks_obtained]"
                                   class="form-control form-control-sm marks-input marks-input-{{ $s->id }}"
                                   min="0"
                                   max="{{ $exam->total_marks }}"
                                   step="0.5"
                                   placeholder="0 – {{ $exam->total_marks }}"
                                   value="{{ $existingResult && !$existingResult->is_absent ? $existingResult->marks_obtained : '' }}"
                                   @disabled($existingResult && $existingResult->is_absent)>
                            <div class="form-text" style="font-size:.68rem">Out of {{ $exam->total_marks }} | Pass: {{ $exam->passing_marks }}</div>
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
        <div class="card-footer d-flex gap-2 flex-wrap align-items-center">
            <button type="submit" class="btn btn-success btn-lg px-4">
                <i class="bi bi-save me-2"></i>Save All Results
            </button>
            <a href="{{ route('teacher.exams.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Exams
            </a>
            <span class="text-muted small ms-auto">
                <i class="bi bi-info-circle me-1"></i>
                Marks out of range will be highlighted in red
            </span>
        </div>
    </div>
</form>
@endif

@endsection

@push('scripts')
<script>
// Absent checkbox disables marks input
document.querySelectorAll('.absent-cb').forEach(cb => {
    cb.addEventListener('change', function() {
        const sid   = this.dataset.student;
        const input = document.querySelector(`.marks-input-${sid}`);
        const row   = this.closest('tr');
        if (input) {
            input.disabled = this.checked;
            if (this.checked) {
                input.value = '';
                row.classList.add('table-warning');
            } else {
                row.classList.remove('table-warning');
            }
        }
    });
    // Init state
    if (cb.checked) {
        const row = cb.closest('tr');
        if (row) row.classList.add('table-warning');
    }
});

// Highlight row red if marks out of range
document.querySelectorAll('.marks-input').forEach(input => {
    input.addEventListener('input', function() {
        const max = parseFloat(this.getAttribute('max'));
        const val = parseFloat(this.value);
        const row = this.closest('tr');
        if (!isNaN(val) && (val < 0 || val > max)) {
            row.classList.add('table-danger');
            this.classList.add('is-invalid');
        } else {
            row.classList.remove('table-danger');
            this.classList.remove('is-invalid');
        }
    });
});
</script>
@endpush
