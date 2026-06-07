@extends('layouts.admin')
@section('title', 'Leave Applications')

@section('content')
<div class="container-fluid py-4">
  <h4 class="mb-4">Student Leave Applications</h4>

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
    <div class="col-auto">
      <a href="{{ route('chair.students.leaves') }}" class="btn btn-outline-secondary">Clear</a>
    </div>
  </form>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-dark">
            <tr><th>Student</th><th>Batch</th><th>Period</th><th>Reason</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
            @forelse($leaves as $leave)
              <tr>
                <td>
                  <div class="fw-semibold">{{ $leave->student->user->name ?? '—' }}</div>
                  <small class="text-muted">{{ $leave->student->enrollment_number ?? '' }}</small>
                </td>
                <td>{{ $leave->student->batch->name ?? '—' }}</td>
                <td>
                  <div class="small">{{ $leave->from_date }} →</div>
                  <div class="small">{{ $leave->to_date }}</div>
                </td>
                <td class="small" style="max-width:200px">{{ Str::limit($leave->reason, 60) }}</td>
                <td>
                  <span class="badge bg-{{ $leave->status==='approved'?'success':($leave->status==='rejected'?'danger':'warning text-dark') }}">
                    {{ ucfirst($leave->status) }}
                  </span>
                </td>
                <td>
                  @if($leave->status === 'pending')
                    <div class="d-flex gap-1">
                      <form method="POST" action="{{ route('chair.students.leaves.approve', $leave) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">Approve</button>
                      </form>
                      <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="document.getElementById('reject-{{ $leave->id }}').classList.toggle('d-none')">Reject</button>
                    </div>
                    <div id="reject-{{ $leave->id }}" class="d-none mt-2">
                      <form method="POST" action="{{ route('chair.students.leaves.reject', $leave) }}">
                        @csrf
                        <div class="input-group">
                          <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Reason" required>
                          <button type="submit" class="btn btn-sm btn-danger">Confirm</button>
                        </div>
                      </form>
                    </div>
                  @else
                    <small class="text-muted">{{ $leave->review_remarks ?? '—' }}</small>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-4">No leave applications found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($leaves->hasPages())
      <div class="card-footer">{{ $leaves->withQueryString()->links() }}</div>
    @endif
  </div>
</div>
@endsection
