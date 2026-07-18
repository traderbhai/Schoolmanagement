@extends('layouts.admin')
@section('title','Hall Tickets')
@section('page-title','Hall Tickets')
@section('content')
<h1 class="h4 mb-3">Hall Tickets</h1>
<div class="row justify-content-center"><div class="col-lg-10">
<div class="card mb-4">
  <div class="card-header bg-transparent fw-semibold">Select Exam to Generate Hall Tickets</div>
  <div class="card-body">
    <form method="GET" class="row g-3">
      <div class="col-md-8">
        <label class="form-label small fw-semibold">Upcoming Exam</label>
        <select aria-label="Exam" name="exam_id" class="form-select" required>
          <option value="">- Select Exam -</option>
          @foreach($exams as $e)
          <option value="{{ $e->id }}" {{ request('exam_id')==$e->id?'selected':'' }}>
            {{ $e->name }} - {{ $e->subject?->name }} ({{ $e->exam_date->format('d M Y') }})
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
      <span class="text-muted small ms-2">{{ $selectedExam->subject?->name }} - {{ $selectedExam->exam_date->format('d M Y') }}</span>
    </div>
    <span class="badge bg-primary">{{ $students->count() }} hall-ticket ready</span>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-light">
        <tr><th scope="col" class="ps-3">#</th><th scope="col">Name</th><th scope="col">Enrollment No.</th><th scope="col">Program</th><th scope="col" class="text-end pe-3">Hall Ticket</th></tr>
      </thead>
      <tbody>
        @forelse($students as $s)
        <tr>
          <td class="ps-3">{{ $loop->iteration }}</td>
          <td class="fw-medium">{{ $s->user?->name ?? '-' }}</td>
          <td class="small">{{ $s->enrollment_number ?? '-' }}</td>
          <td class="small text-muted">{{ $selectedExam->program?->name }}</td>
          <td class="text-end pe-3">
            <a rel="noopener" href="{{ route('exam-cell.hall-ticket.download', [$selectedExam, $s]) }}"
               class="btn btn-sm btn-outline-primary py-0 px-2" target="_blank">
              <i class="bi bi-download me-1"></i>PDF
            </a>
          </td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-center text-muted py-4">No approved students found for this exam.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card mt-4">
  <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Registration Review</span>
    <span class="badge bg-secondary">{{ $registrations->count() }} registration(s)</span>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th scope="col" class="ps-3">Student</th>
          <th scope="col">Eligibility</th>
          <th scope="col">Status</th>
          <th scope="col">Remarks</th>
          <th scope="col" class="text-end pe-3">Review</th>
        </tr>
      </thead>
      <tbody>
        @forelse($registrations as $registration)
        <tr>
          <td class="ps-3">
            <div class="fw-medium">{{ $registration->student?->user?->name ?? '-' }}</div>
            <div class="text-muted small">{{ $registration->student?->enrollment_number ?? '-' }}</div>
          </td>
          <td class="small">
            <div class="{{ $registration->fee_cleared ? 'text-success' : 'text-danger' }}">
              Fee: {{ $registration->fee_cleared ? 'Clear' : 'Blocked' }}
            </div>
            <div class="{{ $registration->attendance_eligible ? 'text-success' : 'text-danger' }}">
              Attendance: {{ $registration->attendance_eligible ? 'Eligible' : 'Blocked' }}
            </div>
          </td>
          <td>
            <span class="badge bg-{{ $registration->status === 'approved' ? 'success' : ($registration->status === 'rejected' ? 'danger' : 'warning text-dark') }}">
              {{ ucfirst($registration->status) }}
            </span>
          </td>
          <td class="small text-muted">{{ $registration->remarks ?: '-' }}</td>
          <td class="text-end pe-3">
            @if($registration->status === 'pending')
              <form method="POST" action="{{ route('exam-cell.registrations.review', $registration) }}" class="d-inline-flex gap-2 justify-content-end">
                @csrf
                @method('PATCH')
                <input type="hidden" name="action" value="approved">
                <input aria-label="Hall ticket remarks" type="text" name="remarks" class="form-control form-control-sm" placeholder="Optional remarks" style="max-width: 180px">
                <button class="btn btn-sm btn-success py-0 px-2">Approve hall ticket</button>
              </form>
              <form method="POST" action="{{ route('exam-cell.registrations.review', $registration) }}" class="d-inline">
                @csrf
                @method('PATCH')
                <input type="hidden" name="action" value="rejected">
                <button class="btn btn-sm btn-outline-danger py-0 px-2">Reject hall ticket</button>
              </form>
            @else
              <span class="text-muted small">Reviewed by {{ $registration->approver?->name ?? 'Exam Cell' }}</span>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-center text-muted py-4">No registrations submitted yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endif
</div></div>
@endsection
