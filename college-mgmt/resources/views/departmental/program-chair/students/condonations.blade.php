@extends('layouts.admin')
@section('title', 'Attendance Condonations')

@section('content')
<div class="container-fluid py-4">
  <h4 class="mb-4">Attendance Condonation Requests</h4>

  @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif

  <form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
      <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="pending"  @selected(request('status')==='pending')>Pending</option>
        <option value="approved" @selected(request('status')==='approved')>Approved</option>
        <option value="rejected" @selected(request('status')==='rejected')>Rejected</option>
      </select>
    </div>
    <div class="col-auto"><a href="{{ route('chair.students.condonations') }}" class="btn btn-outline-secondary">Clear</a></div>
  </form>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-dark">
            <tr><th>Student</th><th>Subject</th><th>Reason</th><th>Sessions Req.</th><th>Status</th><th>Action</th></tr>
          </thead>
          <tbody>
            @forelse($condonations as $c)
              <tr>
                <td>
                  <div class="fw-semibold">{{ $c->student->user->name ?? '—' }}</div>
                  <small class="text-muted">{{ $c->student->batch->name ?? '' }}</small>
                </td>
                <td>{{ $c->subject->name ?? '—' }}</td>
                <td class="small" style="max-width:180px">{{ Str::limit($c->reason, 50) }}</td>
                <td class="text-center">{{ $c->sessions_requested }}</td>
                <td>
                  <span class="badge bg-{{ $c->status==='approved'?'success':($c->status==='rejected'?'danger':'warning text-dark') }}">
                    {{ ucfirst($c->status) }}
                    @if($c->status==='approved') ({{ $c->sessions_condoned }})@endif
                  </span>
                </td>
                <td>
                  @if($c->status === 'pending')
                    <button class="btn btn-sm btn-outline-primary" onclick="document.getElementById('cond-{{ $c->id }}').classList.toggle('d-none')">Review</button>
                    <div id="cond-{{ $c->id }}" class="d-none mt-2" style="width:280px">
                      <form method="POST" action="{{ route('chair.students.condonations.approve', $c) }}" class="mb-1">
                        @csrf
                        <div class="input-group input-group-sm mb-1">
                          <span class="input-group-text">Sessions</span>
                          <input type="number" name="sessions_condoned" class="form-control" min="1" max="{{ $c->sessions_requested }}" value="{{ $c->sessions_requested }}" required>
                        </div>
                        <input type="text" name="remarks" class="form-control form-control-sm mb-1" placeholder="Remarks">
                        <button type="submit" class="btn btn-sm btn-success w-100">Approve</button>
                      </form>
                      <form method="POST" action="{{ route('chair.students.condonations.reject', $c) }}">
                        @csrf
                        <div class="input-group input-group-sm">
                          <input type="text" name="remarks" class="form-control" placeholder="Rejection reason" required>
                          <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                        </div>
                      </form>
                    </div>
                  @else
                    <small class="text-muted">{{ $c->remarks ?? '—' }}</small>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-4">No condonation requests found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($condonations->hasPages())
      <div class="card-footer">{{ $condonations->withQueryString()->links() }}</div>
    @endif
  </div>
</div>
@endsection
