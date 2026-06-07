@extends('layouts.admin')
@section('title','Hall Tickets')
@section('page-title','Hall Tickets')
@section('content')
<div class="row justify-content-center"><div class="col-lg-8">
<div class="card mb-4">
  <div class="card-header bg-transparent fw-semibold">Select Exam to Generate Hall Tickets</div>
  <div class="card-body">
    <form method="GET" class="row g-3">
      <div class="col-md-8">
        <label class="form-label small fw-semibold">Upcoming Exam</label>
        <select name="exam_id" class="form-select" required>
          <option value="">— Select Exam —</option>
          @foreach($exams as $e)
          <option value="{{ $e->id }}" {{ request('exam_id')==$e->id?'selected':'' }}>
            {{ $e->name }} — {{ $e->subject?->name }} ({{ $e->exam_date->format('d M Y') }})
          </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100">Load Students</button>
      </div>
    </form>
  </div>
</div>

@if($selectedExam)
<div class="card">
  <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
    <div>
      <span class="fw-semibold">{{ $selectedExam->name }}</span>
      <span class="text-muted small ms-2">{{ $selectedExam->subject?->name }} · {{ $selectedExam->exam_date->format('d M Y') }}</span>
    </div>
    <span class="badge bg-primary">{{ $students->count() }} students</span>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-light">
        <tr><th class="ps-3">#</th><th>Name</th><th>Enrollment No.</th><th>Program</th><th class="text-end pe-3">Hall Ticket</th></tr>
      </thead>
      <tbody>
        @forelse($students as $s)
        <tr>
          <td class="ps-3">{{ $loop->iteration }}</td>
          <td class="fw-medium">{{ $s->user?->name ?? '—' }}</td>
          <td class="small">{{ $s->enrollment_number ?? '—' }}</td>
          <td class="small text-muted">{{ $selectedExam->program?->name }}</td>
          <td class="text-end pe-3">
            <a href="{{ route('exam-cell.hall-ticket.download', [$selectedExam, $s]) }}"
               class="btn btn-sm btn-outline-primary py-0 px-2" target="_blank">
              <i class="bi bi-download me-1"></i>PDF
            </a>
          </td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-center text-muted py-4">No students found for this exam.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endif
</div></div>
@endsection
