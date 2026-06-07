@extends('layouts.admin')
@section('title', 'Student Grievances')

@section('content')
<div class="container-fluid py-4">
  <h4 class="mb-4">Student Grievances</h4>

  @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif

  <form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
      <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="open"         @selected(request('status')==='open')>Open</option>
        <option value="under_review" @selected(request('status')==='under_review')>Under Review</option>
        <option value="escalated"    @selected(request('status')==='escalated')>Escalated</option>
        <option value="resolved"     @selected(request('status')==='resolved')>Resolved</option>
      </select>
    </div>
    <div class="col-auto"><a href="{{ route('chair.students.grievances') }}" class="btn btn-outline-secondary">Clear</a></div>
  </form>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-dark">
            <tr><th>Student</th><th>Category</th><th>Subject</th><th>Status</th><th>Filed</th><th>Action</th></tr>
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
                  <button class="btn btn-sm btn-outline-primary" onclick="document.getElementById('griev-{{ $g->id }}').classList.toggle('d-none')">Update</button>
                  <div id="griev-{{ $g->id }}" class="d-none mt-2" style="width:280px">
                    <form method="POST" action="{{ route('chair.students.grievances.update', $g) }}">
                      @csrf
                      <select name="status" class="form-select form-select-sm mb-1">
                        <option value="open"         @selected($g->status==='open')>Open</option>
                        <option value="under_review" @selected($g->status==='under_review')>Under Review</option>
                        <option value="escalated"    @selected($g->status==='escalated')>Escalate to HOD</option>
                        <option value="resolved"     @selected($g->status==='resolved')>Resolved</option>
                      </select>
                      <textarea name="resolution_notes" class="form-control form-control-sm mb-1" rows="2" placeholder="Resolution notes">{{ $g->resolution_notes }}</textarea>
                      <button type="submit" class="btn btn-sm btn-primary w-100">Save</button>
                    </form>
                  </div>
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
