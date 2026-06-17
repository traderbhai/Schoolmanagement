@extends('layouts.admin')
@section('title','Enter Results')
@section('page-title','Enter Exam Results')
@section('content')
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row">
            <div class="col"><span class="fw-semibold">{{ $exam->name }}</span> &nbsp;·&nbsp; <span class="text-muted">{{ $exam->subject->name }}</span> &nbsp;·&nbsp; <span class="text-muted">{{ $exam->exam_date->format('d M Y') }}</span></div>
            <div class="col-auto"><span class="badge bg-secondary">Total: {{ $exam->total_marks }} | Pass: {{ $exam->passing_marks }}</span></div>
        </div>
    </div>
</div>
@if($exam->published_at)
    <div class="alert alert-info">
        <strong>Results published.</strong> This grade sheet is locked. Use the Exam Cell appeal/correction workflow for post-publication changes.
    </div>
@endif
<form method="POST" action="{{ route('admin.exams.results.save', $exam) }}">
    @csrf
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead><tr><th>#</th><th>Student</th><th>Enrollment</th><th>Absent?</th><th>Marks (out of {{ $exam->total_marks }})</th><th>Grade</th><th>Remarks</th></tr></thead>
                <tbody>
                @foreach($students as $s)
                @php $existingResult = $s->examResults->first(); @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="fw-semibold">{{ $s->user->name }}</td>
                    <td><code>{{ $s->enrollment_number }}</code></td>
                    <td class="text-center">
                        <input type="checkbox" name="results[{{ $s->id }}][is_absent]" value="1" class="form-check-input absent-cb" data-student="{{ $s->id }}" @checked($existingResult && $existingResult->is_absent) @disabled($exam->published_at)>
                    </td>
                    <td>
                        <input type="number" name="results[{{ $s->id }}][marks]" class="form-control form-control-sm marks-input-{{ $s->id }}" style="max-width:100px" min="0" max="{{ $exam->total_marks }}" step="0.5" value="{{ $existingResult && !$existingResult->is_absent ? $existingResult->marks_obtained : '' }}" @disabled($exam->published_at || ($existingResult && $existingResult->is_absent))>
                    </td>
                    <td>
                        <input type="text" name="results[{{ $s->id }}][grade]" class="form-control form-control-sm" style="max-width:70px" placeholder="A/B/C..." value="{{ $existingResult ? $existingResult->grade : '' }}" @disabled($exam->published_at)>
                    </td>
                    <td>
                        <input type="text" name="results[{{ $s->id }}][remarks]" class="form-control form-control-sm" placeholder="Optional" value="{{ $existingResult ? $existingResult->remarks : '' }}" @disabled($exam->published_at)>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-success" @disabled($exam->published_at)>Save All Results</button>
            <a href="{{ route('admin.exams.show', $exam) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</form>
@push('scripts')
<script>
document.querySelectorAll('.absent-cb').forEach(cb => {
    cb.addEventListener('change', function() {
        const sid = this.dataset.student;
        const input = document.querySelector(`.marks-input-${sid}`);
        if (input) input.disabled = this.checked;
        if (this.checked && input) input.value = '';
    });
});
</script>
@endpush
@endsection
