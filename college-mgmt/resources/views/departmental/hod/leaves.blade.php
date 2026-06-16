@extends('layouts.admin')
@section('title','Leave Applications')
@section('page-title','Leave Applications')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Student Leave Applications</h5>
    <form method="GET" class="d-flex gap-2">
        <select name="status" class="form-select form-select-sm" style="width:140px;" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="pending" {{ request('status')==='pending'?'selected':'' }}>Pending</option>
            <option value="approved" {{ request('status')==='approved'?'selected':'' }}>Approved</option>
            <option value="rejected" {{ request('status')==='rejected'?'selected':'' }}>Rejected</option>
        </select>
    </form>
</div>
<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-light">
        <tr><th class="ps-3">Student</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th><th class="text-end pe-3">Action</th></tr>
      </thead>
      <tbody>
        @forelse($leaves as $leave)
        <tr>
          <td class="ps-3 fw-medium">{{ $leave->student?->user?->name ?? '—' }}</td>
          <td class="small">{{ $leave->from_date->format('d M Y') }}</td>
          <td class="small">{{ $leave->to_date->format('d M Y') }}</td>
          <td>{{ $leave->getDaysCount() }}</td>
          <td class="small text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $leave->reason }}">{{ $leave->reason }}</td>
          <td>
            @php $colors=['pending'=>'warning','approved'=>'success','rejected'=>'danger']; @endphp
            <span class="badge bg-{{ $colors[$leave->status] ?? 'secondary' }}">{{ ucfirst($leave->status) }}</span>
          </td>
          <td class="text-end pe-3">
            @if($leave->status === 'pending')
            <button class="btn btn-xs btn-outline-success py-0 px-2" style="font-size:.75rem;"
                data-review-url="{{ route('hod.leaves.review', $leave) }}"
                data-review-action="approved"
                onclick="openReview(this)">Approve</button>
            <button class="btn btn-xs btn-outline-danger py-0 px-2 ms-1" style="font-size:.75rem;"
                data-review-url="{{ route('hod.leaves.review', $leave) }}"
                data-review-action="rejected"
                onclick="openReview(this)">Reject</button>
            @else
            <span class="text-muted small">{{ $leave->review_remarks ?? '—' }}</span>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-center text-muted py-4">No leave applications found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
{{ $leaves->links() }}

{{-- Review Modal --}}
<div class="modal fade" id="reviewModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title mb-0">Review Leave</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="reviewForm" method="POST">
        @csrf
        <input type="hidden" name="action" id="reviewAction">
        <div class="modal-body">
          <label class="form-label small fw-semibold">Remarks (optional)</label>
          <textarea name="remarks" class="form-control" rows="3" placeholder="Add remarks..."></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-primary" id="reviewSubmitBtn">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script>
function openReview(button) {
    const action = button.dataset.reviewAction;
    document.getElementById('reviewForm').action = button.dataset.reviewUrl;
    document.getElementById('reviewAction').value = action;
    document.getElementById('reviewSubmitBtn').textContent = action === 'approved' ? 'Approve' : 'Reject';
    document.getElementById('reviewSubmitBtn').className = 'btn btn-sm btn-' + (action === 'approved' ? 'success' : 'danger');
    new bootstrap.Modal(document.getElementById('reviewModal')).show();
}
</script>
@endpush
