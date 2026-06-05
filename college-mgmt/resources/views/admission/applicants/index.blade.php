@extends('layouts.admin')

@section('title', 'All Applicants — Admission CRM')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">All Applicants</h2>
    <a href="{{ route('admission.dashboard') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-speedometer2 me-1"></i> Dashboard
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Name / Email / App#" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="program_id" class="form-select form-select-sm">
                    <option value="">All Programs</option>
                    @foreach($programs as $p)
                        <option value="{{ $p->id }}" @selected(request('program_id') == $p->id)>{{ $p->abbreviation }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s }}" @selected(request('status') == $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    @foreach(['general' => 'General','obc' => 'OBC','obc_nc' => 'OBC-NC','sc' => 'SC','st' => 'ST','ews' => 'EWS','pwd' => 'PWD','nri' => 'NRI','management_quota' => 'Mgmt Quota'] as $val => $lbl)
                        <option value="{{ $val }}" @selected(request('category') == $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}" placeholder="From">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}" placeholder="To">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</div>

{{-- Bulk Actions Form --}}
<form id="bulkForm" action="{{ route('admission.applicants.bulk-action') }}" method="POST">
    @csrf
    <div id="bulkBar" class="alert alert-warning d-none mb-3 d-flex align-items-center gap-3">
        <span class="fw-semibold"><span id="selectedCount">0</span> selected</span>
        <select name="action" class="form-select form-select-sm w-auto" required>
            <option value="">-- Bulk Action --</option>
            <option value="under_review">Move to Under Review</option>
            <option value="shortlisted">Move to Shortlisted</option>
            <option value="rejected">Move to Rejected</option>
            <option value="withdrawn">Mark Withdrawn</option>
        </select>
        <button type="submit" class="btn btn-warning btn-sm">Apply</button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="40"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                        <th>Name / Email</th>
                        <th>App #</th>
                        <th>Program</th>
                        <th>Category</th>
                        <th>Entrance Exam</th>
                        <th>Status</th>
                        <th>Applied At</th>
                        <th>Last Interaction</th>
                        <th>Next Follow-up</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applicants as $applicant)
                    <tr>
                        <td><input type="checkbox" name="applicant_ids[]" value="{{ $applicant->id }}" class="form-check-input row-check"></td>
                        <td>
                            <div class="fw-semibold">{{ $applicant->user->name ?? 'N/A' }}</div>
                            <div class="text-muted small">{{ $applicant->user->email ?? '' }}</div>
                        </td>
                        <td class="font-monospace small">{{ $applicant->application_number }}</td>
                        <td class="small">{{ $applicant->program->abbreviation ?? 'N/A' }}</td>
                        <td class="small">
                            <span class="badge bg-light text-dark border">{{ $applicant->category_label }}</span>
                            @if($applicant->category_certificate_verified)
                                <i class="bi bi-patch-check-fill text-success ms-1" title="Certificate Verified"></i>
                            @endif
                        </td>
                        <td class="small">
                            @if($applicant->entrance_exam_type && $applicant->entrance_exam_type !== 'none')
                                <span class="badge bg-secondary">{{ $applicant->entrance_exam_label }}</span>
                                @if($applicant->entrance_exam_score)
                                    <span class="text-muted ms-1">{{ $applicant->entrance_exam_score }}</span>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td><span class="{{ $applicant->status_badge }}">{{ $applicant->status_label }}</span></td>
                        <td class="small">{{ $applicant->applied_at ? $applicant->applied_at->format('d M Y') : '—' }}</td>
                        <td class="small">
                            @if($applicant->counsellingLogs->first())
                                <span class="badge bg-secondary">{{ ucfirst(str_replace('_',' ',$applicant->counsellingLogs->first()->outcome)) }}</span>
                                <div style="font-size:0.75rem" class="text-muted">{{ $applicant->counsellingLogs->first()->created_at->diffForHumans() }}</div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="small">
                            @if($applicant->counsellingLogs->first()?->next_followup_date)
                                <span class="badge bg-warning text-dark">{{ $applicant->counsellingLogs->first()->next_followup_date->format('d M') }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admission.applicants.show', $applicant) }}" class="btn btn-sm btn-outline-primary py-0 px-2">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="11" class="text-center text-muted py-4">No applicants found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-transparent">
            {{ $applicants->links() }}
        </div>
    </div>
</form>

@push('scripts')
<script>
document.getElementById('checkAll').addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    updateBulkBar();
});
document.querySelectorAll('.row-check').forEach(cb => cb.addEventListener('change', updateBulkBar));
function updateBulkBar() {
    const checked = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('selectedCount').textContent = checked;
    document.getElementById('bulkBar').classList.toggle('d-none', checked === 0);
}
</script>
@endpush
@endsection
