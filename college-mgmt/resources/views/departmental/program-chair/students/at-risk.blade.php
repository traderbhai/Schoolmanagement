@extends('layouts.admin')
@section('title', 'At-Risk Students')

@section('content')
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">At-Risk Students</h4>
    <div class="text-muted small">{{ $currentTerm->name ?? 'Current Term' }}</div>
  </div>

  <form method="GET" class="row g-2 mb-4">
    <div class="col-md-3">
      <select name="program_id" class="form-select" onchange="this.form.submit()">
        <option value="">All Programs</option>
        @foreach($programs as $p)
          <option value="{{ $p->id }}" @selected(request('program_id') == $p->id)>{{ $p->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <select name="batch_id" class="form-select" onchange="this.form.submit()">
        <option value="">All Batches</option>
        @foreach($batches as $b)
          <option value="{{ $b->id }}" @selected(request('batch_id') == $b->id)>{{ $b->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <select name="risk" class="form-select" onchange="this.form.submit()">
        <option value="">All Risk Types</option>
        <option value="attendance" @selected(request('risk')==='attendance')>Attendance &lt;75%</option>
        <option value="academic"   @selected(request('risk')==='academic')>Academic (CGPA &lt;5)</option>
        <option value="arrear"     @selected(request('risk')==='arrear')>Arrear</option>
        <option value="financial"  @selected(request('risk')==='financial')>Fee Dues</option>
      </select>
    </div>
    <div class="col-md-3">
      <a href="{{ route('chair.students.at-risk') }}" class="btn btn-outline-secondary">Clear</a>
    </div>
  </form>

  <div class="mb-3">
    <span class="badge bg-danger fs-6">{{ $atRisk->count() }}</span> students flagged at-risk
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-dark">
            <tr>
              <th>Student</th>
              <th>Batch / Program</th>
              <th>Risk Flags</th>
              <th>Attendance Issues</th>
              <th>Mentor</th>
            </tr>
          </thead>
          <tbody>
            @forelse($atRisk as $s)
              <tr>
                <td>
                  <div class="fw-semibold">{{ $s->user->name ?? '—' }}</div>
                  <small class="text-muted">{{ $s->enrollment_number }}</small>
                </td>
                <td>
                  <div>{{ $s->batch->name ?? '—' }}</div>
                  <small class="text-muted">{{ $s->program->name ?? '' }}</small>
                </td>
                <td>
                  @foreach($s->risks as $risk)
                    <span class="badge bg-{{ $risk==='attendance'?'warning text-dark':($risk==='arrear'?'danger':($risk==='financial'?'dark':'secondary')) }} me-1">
                      {{ ucfirst($risk) }}
                    </span>
                  @endforeach
                </td>
                <td>
                  @if(isset($s->low_att_count) && $s->low_att_count > 0)
                    <span class="text-danger">{{ $s->low_att_count }} subject(s) below 75%</span>
                  @else
                    <span class="text-muted small">—</span>
                  @endif
                </td>
                <td>{{ $s->mentor->user->name ?? '<span class="text-muted small">Unassigned</span>' }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted py-5">
                <i class="bi bi-check-circle display-4 d-block mb-2 text-success opacity-50"></i>
                No at-risk students found.
              </td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
