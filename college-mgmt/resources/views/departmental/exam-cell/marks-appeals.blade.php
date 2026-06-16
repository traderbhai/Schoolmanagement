@extends('layouts.admin')
@section('title','Marks Appeals')
@section('page-title','Marks Appeals')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">Marks Appeals Inbox</h5>
</div>

@if(method_exists($appeals,'total') ? $appeals->total() === 0 : $appeals->isEmpty())
<div class="card"><div class="card-body text-center py-5 text-muted">
  <i class="bi bi-inbox fs-2 d-block mb-2"></i>No marks appeals found.
</div></div>
@else
<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-light">
        <tr><th class="ps-3">Student</th><th>Exam / Subject</th><th>Current Marks</th><th>Reason</th><th>Status</th><th class="text-end pe-3">Action</th></tr>
      </thead>
      <tbody>
        @foreach($appeals as $appeal)
        @php $colors=['pending'=>'warning','resolved'=>'success','rejected'=>'danger','under_review'=>'info']; @endphp
        <tr>
          <td class="ps-3 fw-medium">{{ $appeal->student?->user?->name ?? '—' }}</td>
          <td class="small">{{ $appeal->examResult?->exam?->subject?->name ?? '-' }}</td>
          <td>{{ $appeal->examResult?->marks_obtained ?? '-' }}</td>
          <td class="small text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $appeal->reason ?? '—' }}</td>
          <td><span class="badge bg-{{ $colors[$appeal->status ?? 'pending'] ?? 'secondary' }}">{{ ucfirst($appeal->status ?? 'pending') }}</span></td>
          <td class="text-end pe-3">
            @if(($appeal->status ?? 'pending') === 'pending' || ($appeal->status ?? '') === 'under_review')
            <button class="btn btn-sm btn-outline-primary py-0 px-2"
                    data-review-url="{{ route('exam-cell.marks-appeals.review', $appeal) }}"
                    data-current-marks="{{ $appeal->examResult?->marks_obtained ?? 0 }}"
                    onclick="openReview(this)">
              Review
            </button>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@if(method_exists($appeals,'links')) {{ $appeals->links() }} @endif
@endif

{{-- Review Modal --}}
<div class="modal fade" id="reviewModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title mb-0">Review Marks Appeal</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="reviewForm" method="POST">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Decision</label>
            <select name="action" class="form-select" required>
              <option value="under_review">Under Review</option>
              <option value="approved">Approved (revise marks)</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Revised Marks (if approved)</label>
            <input type="number" name="revised_marks" id="revisedMarks" class="form-control" min="0" step="0.5">
          </div>
          <div>
            <label class="form-label small fw-semibold">Remarks</label>
            <textarea name="remarks" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-primary">Submit Review</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script>
function openReview(button) {
    document.getElementById('reviewForm').action = button.dataset.reviewUrl;
    document.getElementById('revisedMarks').value = button.dataset.currentMarks || 0;
    new bootstrap.Modal(document.getElementById('reviewModal')).show();
}
</script>
@endpush
