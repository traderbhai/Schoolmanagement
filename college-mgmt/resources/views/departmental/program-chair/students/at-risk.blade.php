@extends('layouts.admin')
@section('title', 'At-Risk Students')

@section('content')
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-1">At-Risk Students</h4>
      <div class="text-muted small">{{ $currentTerm->name ?? 'Current Term' }}</div>
    </div>
    <a href="{{ route('chair.students.at-risk.export', request()->query()) }}" class="btn btn-outline-success btn-sm">Export Current View</a>
  </div>

  <form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6">
          <label class="form-label small text-muted mb-1" for="atRiskSearch">Search</label>
          <input id="atRiskSearch" type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Student, enrollment, program">
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small text-muted mb-1" for="atRiskProgram">Program</label>
          <select id="atRiskProgram" name="program_id" class="form-select" onchange="this.form.submit()">
            <option value="">All Programs</option>
            @foreach($programs as $p)
              <option value="{{ $p->id }}" @selected(request('program_id') == $p->id)>{{ $p->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small text-muted mb-1" for="atRiskBatch">Batch</label>
          <select id="atRiskBatch" name="batch_id" class="form-select" onchange="this.form.submit()">
            <option value="">All Batches</option>
            @foreach($batches as $b)
              <option value="{{ $b->id }}" @selected(request('batch_id') == $b->id)>{{ $b->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small text-muted mb-1" for="atRiskType">Risk</label>
          <select id="atRiskType" name="risk" class="form-select" onchange="this.form.submit()">
            <option value="">All Risk Types</option>
            <option value="attendance" @selected(request('risk') === 'attendance')>Attendance below 75%</option>
            <option value="academic" @selected(request('risk') === 'academic')>Academic</option>
            <option value="arrear" @selected(request('risk') === 'arrear')>Arrear</option>
            <option value="financial" @selected(request('risk') === 'financial')>Fee Dues</option>
          </select>
        </div>
        <div class="col-lg-3 col-md-12 d-flex gap-2">
          <button class="btn btn-primary" type="submit">Apply filters</button>
          <a href="{{ route('chair.students.at-risk') }}" class="btn btn-outline-secondary">Clear</a>
        </div>
      </div>
      <div class="mt-2 small text-muted">
        <span class="fw-semibold">Visible filter summary:</span>
        @forelse($filterSummary as $filter)
          <span class="badge text-bg-light border">{{ $filter }}</span>
        @empty
          <span class="badge text-bg-light border">All scoped at-risk students</span>
        @endforelse
      </div>
    </div>
  </form>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <div><span class="badge bg-danger fs-6">{{ $atRiskStudents->total() }}</span> students flagged at-risk</div>
    <div class="small text-muted">Showing {{ $atRiskStudents->firstItem() ?? 0 }}-{{ $atRiskStudents->lastItem() ?? 0 }} of {{ $atRiskStudents->total() }}</div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-dark">
            <tr>
              <th scope="col"><a class="link-light" href="{{ route('chair.students.at-risk', array_merge(request()->query(), ['sort' => 'student', 'direction' => request('direction') === 'desc' ? 'asc' : 'desc'])) }}">Student</a></th>
              <th scope="col"><a class="link-light" href="{{ route('chair.students.at-risk', array_merge(request()->query(), ['sort' => 'program', 'direction' => request('direction') === 'desc' ? 'asc' : 'desc'])) }}">Batch / Program</a></th>
              <th scope="col">Risk Flags</th>
              <th scope="col"><a class="link-light" href="{{ route('chair.students.at-risk', array_merge(request()->query(), ['sort' => 'attendance_issues', 'direction' => request('direction') === 'desc' ? 'asc' : 'desc'])) }}">Attendance Issues</a></th>
              <th scope="col">Mentor</th>
            </tr>
          </thead>
          <tbody>
            @forelse($atRiskStudents as $s)
              <tr>
                <td>
                  <div class="fw-semibold">{{ $s->user->name ?? '-' }}</div>
                  <small class="text-muted">{{ $s->enrollment_number }}</small>
                </td>
                <td>
                  <div>{{ $s->batch->name ?? '-' }}</div>
                  <small class="text-muted">{{ $s->program->name ?? '' }}</small>
                </td>
                <td>
                  @foreach($s->risks as $risk)
                    <span class="badge bg-{{ $risk === 'attendance' ? 'warning text-dark' : ($risk === 'arrear' ? 'danger' : ($risk === 'financial' ? 'dark' : 'secondary')) }} me-1">
                      {{ ucfirst($risk) }}
                    </span>
                  @endforeach
                </td>
                <td>
                  @if(($s->low_att_count ?? 0) > 0)
                    <span class="text-danger">{{ $s->low_att_count }} subject(s) below 75%</span>
                  @else
                    <span class="text-muted small">-</span>
                  @endif
                </td>
                <td>
                  @if($s->mentor)
                    {{ $s->mentor->name }}
                  @else
                    <span class="text-muted small">Unassigned</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-muted py-5">
                  <i class="bi bi-check-circle display-4 d-block mb-2 text-success opacity-50"></i>
                  No at-risk students found for the current filters.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($atRiskStudents->hasPages())
      <div class="card-footer bg-white">
        {{ $atRiskStudents->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
