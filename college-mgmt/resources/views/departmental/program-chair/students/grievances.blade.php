@extends('layouts.admin')
@section('title', 'Student Grievances')

@section('content')
<div class="container-fluid py-4">
  <h4 class="mb-4">Student Grievances</h4>

  @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif

  <form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
      <select aria-label="Status" name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="open"         @selected(request('status')==='open')>Open</option>
        <option value="under_review" @selected(request('status')==='under_review')>Under Review</option>
        <option value="escalated"    @selected(request('status')==='escalated')>Escalated</option>
        <option value="resolved"     @selected(request('status')==='resolved')>Resolved</option>
        <option value="closed"       @selected(request('status')==='closed')>Closed</option>
      </select>
    </div>
    <div class="col-auto"><a href="{{ route('chair.students.grievances') }}" class="btn btn-outline-secondary">Clear</a></div>
  </form>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-dark">
            <tr><th scope="col">Student</th><th scope="col">Category</th><th scope="col">Subject</th><th scope="col">Status</th><th scope="col">Filed</th><th scope="col">Action</th></tr>
          </thead>
          <tbody>
            @forelse($grievances as $g)
              <tr>
                <td>
                  <div class="fw-semibold">{{ $g->student->user->name ?? '—' }}</div>
                  <small class="text-muted">{{ $g->student->batch->name ?? '' }}</small>
                </td>
                <td>{{ ucfirst(str_replace('_',' ',$g->category ?? '')) }}</td>
                <td class="small" style="max-width:180px">{{ Str::limit($g->subject ?? $g->description, 55) }}</td>
                <td>
                  <span class="badge bg-{{ $g->status==='resolved'?'success':($g->status==='escalated'?'danger':($g->status==='under_review'?'info text-dark':'warning text-dark')) }}">
                    {{ ucfirst(str_replace('_',' ',$g->status)) }}
                  </span>
                </td>
                <td class="small text-muted">{{ $g->created_at->format('d M Y') }}</td>
                <td>
                  @if(in_array($g->status, ['resolved', 'closed'], true))
                    <span class="text-muted small">History locked</span>
                  @else
                    <button class="btn btn-sm btn-outline-primary" onclick="document.getElementById('griev-{{ $g->id }}').classList.toggle('d-none')">Update grievance</button>
                    <div id="griev-{{ $g->id }}" class="d-none mt-2" style="width:280px">
                      <form method="POST" action="{{ route('chair.students.grievances.update', $g) }}">
                        @csrf
                        <select aria-label="Status" name="status" class="form-select form-select-sm mb-1">
                          <option value="open"         @selected($g->status==='open')>Open</option>
                          <option value="under_review" @selected($g->status==='under_review')>Under Review</option>
                          @if(in_array($g->status, ['open', 'under_review'], true))
                            <option value="escalated" @selected($g->status==='escalated')>Escalate to HOD</option>
                          @endif
                          <option value="resolved"     @selected($g->status==='resolved')>Resolved</option>
                        </select>
                        <textarea aria-label="Resolution notes required when resolving" name="resolution_notes" class="form-control form-control-sm mb-1" rows="2" maxlength="1000" placeholder="Resolution notes required when resolving">{{ $g->resolution_notes }}</textarea>
                        <button type="submit" class="btn btn-sm btn-primary w-100">Save grievance</button>
                      </form>
                    </div>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-4">No grievances found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($grievances->hasPages())
      <div class="card-footer">{{ $grievances->withQueryString()->links() }}</div>
    @endif
  </div>
</div>
@endsection
