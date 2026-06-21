@extends('layouts.admin')
@section('title', 'Substitution Management')

@section('content')
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Substitution Management</h4>
    <a href="{{ route('chair.timetable.builder') }}" class="btn btn-outline-secondary btn-sm">← Builder</a>
  </div>

  @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif

  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Record Substitution / Cancellation</div>
        <div class="card-body">
          <form method="POST" action="{{ route('chair.timetable.substitutions.store') }}">
            @csrf
            <div class="mb-3">
              <label class="form-label">Session</label>
              <select name="timetable_entry_id" class="form-select" required>
                <option value="">Select session…</option>
                @foreach($entries as $e)
                  <option value="{{ $e->id }}">
                    {{ ['Mon','Tue','Wed','Thu','Fri','Sat'][$e->day_of_week-1] ?? '' }}
                    · {{ $e->slot->name ?? '' }}
                    · {{ $e->subject->name ?? '?' }}
                    @if($e->batch)({{ $e->batch->name }})@endif
                    · {{ $e->teacher->user->name ?? '—' }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Date</label>
              <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Action</label>
              <select name="action" class="form-select" id="action-sel" onchange="toggleSub(this.value)" required>
                <option value="substitute">Substitute teacher</option>
                <option value="cancelled">Cancel session</option>
                <option value="rescheduled">Rescheduled</option>
              </select>
            </div>
            <div class="mb-3" id="sub-row">
              <label class="form-label">Substitute Teacher</label>
              <select name="substitute_teacher_id" class="form-select">
                <option value="">— None —</option>
                @foreach($teachers as $t)
                  <option value="{{ $t->id }}">{{ $t->user->name ?? $t->id }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Reason</label>
              <input type="text" name="reason" class="form-control" maxlength="300" placeholder="e.g. Faculty on leave">
            </div>
            <button type="submit" class="btn btn-primary w-100">Record</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Recent Records</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light">
                <tr><th>Date</th><th>Session</th><th>Action</th><th>Substitute</th><th>Reason</th></tr>
              </thead>
              <tbody>
                @forelse($recent as $sub)
                  <tr>
                    <td>{{ $sub->date }}</td>
                    <td>
                      <span class="fw-semibold">{{ $sub->entry->subject->name ?? '?' }}</span>
                      <small class="text-muted d-block">{{ $sub->entry->batch->name ?? '' }}</small>
                    </td>
                    <td><span class="badge bg-{{ $sub->action==='cancelled'?'danger':($sub->action==='substitute'?'info text-dark':'warning text-dark') }}">{{ ucfirst($sub->action) }}</span></td>
                    <td>{{ $sub->substitute->user->name ?? '—' }}</td>
                    <td class="small text-muted">{{ Str::limit($sub->reason,40) }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center py-4">
                      <div class="fw-semibold text-dark">No substitution records yet</div>
                      <div class="small text-muted mx-auto mt-1" style="max-width: 540px;">
                        Faculty replacements, cancellations, and rescheduled sessions will appear here after they are recorded for your scoped timetable. Start by selecting an active session on the left, or return to the builder to review available sessions.
                      </div>
                      <div class="mt-3">
                        <a href="{{ route('chair.timetable.builder') }}" class="btn btn-outline-secondary btn-sm">Review timetable builder</a>
                      </div>
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script>
function toggleSub(v){document.getElementById('sub-row').style.display=v==='substitute'?'':'none';}
</script>
@endpush
