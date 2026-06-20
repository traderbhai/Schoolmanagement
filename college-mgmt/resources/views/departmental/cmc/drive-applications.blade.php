@extends('layouts.admin')
@section('title','Drive Applications')
@section('page-title','Drive Applications')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h5 class="mb-0">{{ $drive->title }}</h5>
    <div class="text-muted small">{{ $drive->company?->name }} · {{ $drive->job_role }} · {{ $drive->drive_date?->format('d M Y') }}</div>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('cmc.drives.applications.export', $drive) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Export Current View</a>
    <a href="{{ route('cmc.drives') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>
<div class="text-muted small mb-2">Showing {{ $applications->count() }} application record(s) for this drive.</div>
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-light">
        <tr><th class="ps-3">Student</th><th>Enrollment</th><th>Status</th><th>Package Offered</th><th>Remarks</th><th class="text-end pe-3">Update</th></tr>
      </thead>
      <tbody>
        @forelse($applications as $app)
        @php
          $colors=['applied'=>'secondary','shortlisted'=>'info','interview'=>'primary','selected'=>'success','rejected'=>'danger','withdrawn'=>'secondary'];
          $status = $app->application_status ?? 'applied';
        @endphp
        <tr>
          <td class="ps-3 fw-medium">{{ $app->student?->user?->name ?? '—' }}</td>
          <td class="small">{{ $app->student?->enrollment_number ?? '—' }}</td>
          <td><span class="badge bg-{{ $colors[$status] ?? 'secondary' }}">{{ ucfirst($status) }}</span></td>
          <td class="small">{{ $app->offered_package ?? '—' }}</td>
          <td class="small text-muted">{{ $app->remarks ?? '—' }}</td>
          <td class="text-end pe-3">
            <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                data-update-url="{{ route('cmc.placements.update-status', $app) }}"
                data-status="{{ $status }}"
                data-package="{{ $app->offered_package }}"
                data-remarks="{{ $app->remarks }}"
                onclick="openUpdate(this)">
              <i class="bi bi-pencil"></i>
            </button>
          </td>
        </tr>
        @empty
        <tr><td colspan="6" class="text-center text-muted py-4">No applications yet.</td></tr>
        @endforelse
      </tbody>
    </table>
    </div>
  </div>
</div>

<div class="modal fade" id="updateModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:380px">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title mb-0">Update Application</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="updateForm" method="POST">
        @csrf @method('PATCH')
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Status</label>
            <select name="application_status" id="updateStatus" class="form-select" required>
              @foreach(['applied','shortlisted','interview','selected','rejected','withdrawn'] as $s)
              <option value="{{ $s }}">{{ ucfirst($s) }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Offered Package</label>
            <input type="number" name="offered_package" id="updatePackage" class="form-control" min="0" step="0.01" placeholder="e.g. 6.5">
          </div>
          <div>
            <label class="form-label small fw-semibold">Remarks</label>
            <textarea name="remarks" id="updateRemarks" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script>
function openUpdate(button) {
    document.getElementById('updateForm').action = button.dataset.updateUrl;
    document.getElementById('updateStatus').value = button.dataset.status;
    document.getElementById('updatePackage').value = button.dataset.package || '';
    document.getElementById('updateRemarks').value = button.dataset.remarks || '';
    new bootstrap.Modal(document.getElementById('updateModal')).show();
}
</script>
@endpush
