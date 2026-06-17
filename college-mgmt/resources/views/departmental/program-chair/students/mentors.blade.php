@extends('layouts.admin')
@section('title', 'Mentor Assignment')

@section('content')
<div class="container-fluid py-4">
  <h4 class="mb-4">Mentor Assignment</h4>

  @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif

  {{-- Bulk assign --}}
  <div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Bulk Assign by Batch</div>
    <div class="card-body">
      <form method="POST" action="{{ route('chair.students.mentors.bulk') }}" class="row g-3">
        @csrf
        <div class="col-md-4">
          <select name="batch_id" class="form-select" required>
            <option value="">Select Batch…</option>
            @foreach($batches as $b)
              <option value="{{ $b->id }}">{{ $b->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <select name="mentor_id" class="form-select" required>
            <option value="">Select Mentor…</option>
            @foreach($teachers as $t)
              <option value="{{ $t->id }}">{{ $t->user->name ?? $t->id }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary" onclick="return confirm('Assign mentor to entire batch?')">Bulk Assign</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Filter --}}
  <form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
      <select name="batch_id" class="form-select" onchange="this.form.submit()">
        <option value="">All Batches</option>
        @foreach($batches as $b)
          <option value="{{ $b->id }}" @selected(request('batch_id') == $b->id)>{{ $b->name }}</option>
        @endforeach
      </select>
    </div>
  </form>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-dark">
            <tr><th>Student</th><th>Batch</th><th>Current Mentor</th><th style="width:220px">Assign Mentor</th></tr>
          </thead>
          <tbody>
            @forelse($students as $s)
              <tr>
                <td>
                  <div class="fw-semibold">{{ $s->user->name ?? '—' }}</div>
                  <small class="text-muted">{{ $s->enrollment_number }}</small>
                </td>
                <td>{{ $s->batch->name ?? '—' }}</td>
                <td>
                  @if($s->mentor)
                    {{ $s->mentor->name }}
                  @else
                    <span class="text-muted small">Unassigned</span>
                  @endif
                </td>
                <td>
                  <form method="POST" action="{{ route('chair.students.mentors.assign') }}" class="d-flex gap-1">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $s->id }}">
                    <select name="mentor_id" class="form-select form-select-sm">
                      <option value="">None</option>
                      @foreach($teachers as $t)
                        <option value="{{ $t->id }}" @selected($s->mentor_id == $t->user_id)>{{ $t->user->name ?? $t->id }}</option>
                      @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted py-4">No students found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($students->hasPages())
      <div class="card-footer">{{ $students->withQueryString()->links() }}</div>
    @endif
  </div>
</div>
@endsection
