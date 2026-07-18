@extends('layouts.admin')

@section('title', 'Merit List - ' . $program->name)

@section('content')
@php($canApproveAdmission = app(\App\Services\DepartmentHierarchyService::class)->canApproveAdmission(auth()->user()))
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-list-ol me-2"></i>{{ $program->name }} - Merit List</h3>
                <div class="text-muted small">Ranked applicants for selection, waitlist movement, offer generation, and seat-control decisions.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('admission.merit-list.export', array_merge(['program' => $program->id], request()->only('batch_id'))) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-file-pdf me-1"></i>Export PDF
                </a>
                <a href="{{ route('admission.merit-list.index', $program) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info border-0 shadow-sm mb-4">
    <div class="fw-semibold mb-1"><i class="bi bi-check2-square me-1"></i>Decision workflow</div>
    <div class="small text-muted">Review the filtered rank list, confirm batch and decision scope, apply selected/waitlisted/rejected decisions, then generate offer letters only for finalized selected applicants. Entries connected to active offers are protected by the integrity workflow.</div>
</div>

<form method="GET" class="mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-sm-3">
            <label class="form-label small">Batch</label>
            <select aria-label="Batch" name="batch_id" class="form-select form-select-sm">
                <option value="">All Batches</option>
                @foreach($batches as $b)
                    <option value="{{ $b->id }}" @selected($batchId == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-3">
            <label class="form-label small">Decision</label>
            <select aria-label="Decision" name="decision" class="form-select form-select-sm">
                <option value="">All Decisions</option>
                <option value="pending" @selected($decision == 'pending')>Pending</option>
                <option value="selected" @selected($decision == 'selected')>Selected</option>
                <option value="waitlisted" @selected($decision == 'waitlisted')>Waitlisted</option>
                <option value="rejected" @selected($decision == 'rejected')>Rejected</option>
            </select>
        </div>
        <div class="col-sm-2">
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        </div>
        @if($batchId || $decision)
        <div class="col-sm-2">
            <a href="{{ route('admission.merit-list.show', $program) }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
        </div>
        @endif
    </div>
</form>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap gap-2 align-items-center small">
            <span class="fw-semibold text-dark">Current view:</span>
            <span class="badge bg-light text-dark">Program: {{ $program->name }}</span>
            <span class="badge bg-light text-dark">Batch: {{ optional($batches->firstWhere('id', (int) $batchId))->name ?? 'All Batches' }}</span>
            <span class="badge bg-light text-dark">Decision: {{ $decision ? ucfirst($decision) : 'All Decisions' }}</span>
            <span class="badge bg-light text-dark">Rows: {{ $entries->total() }}</span>
        </div>
    </div>
</div>

@if($canApproveAdmission)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('admission.merit-list.bulk-decide', $program) }}" class="row g-2 align-items-end" onsubmit="return confirm('Apply bulk selected/waitlist decisions for the current program, batch, and filtered rank list? This can affect offer letters, seat holds, waitlist movement, and enrollment readiness.')">
            @csrf
            @if($batchId) <input type="hidden" name="batch_id" value="{{ $batchId }}"> @endif
            <div class="col-sm-3">
                <label class="form-label small fw-semibold">Accept Top N</label>
                <input aria-label="Accept Top" type="number" name="accept_top" class="form-control form-control-sm" min="1" value="10" required>
            </div>
            <div class="col-sm-3">
                <label class="form-label small fw-semibold">Waitlist Next M</label>
                <input aria-label="Waitlist Next" type="number" name="waitlist_next" class="form-control form-control-sm" min="0" value="5">
            </div>
            <div class="col-sm-3">
                <button type="submit" class="btn btn-sm btn-warning">
                    <i class="bi bi-people-fill me-1"></i>Apply Bulk Decision
                </button>
            </div>
            <div class="col-sm-3 small text-muted">Bulk decision uses current rank and batch scope. Locked/final applicants are skipped by the integrity guard.</div>
        </form>
    </div>
</div>
@endif

@if($canApproveAdmission)
<form method="POST" action="{{ route('admission.offer-letters.bulk-generate', $program) }}" id="bulkOfferForm">
    @csrf
    <input type="hidden" name="program_id" value="{{ $program->id }}">
    <div class="d-flex gap-2 mb-3">
        <button type="button" onclick="selectAll()" class="btn btn-outline-secondary btn-sm">Select All</button>
        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Generate offer letters for the selected applicants? Confirm merit decisions, seat capacity, payment deadlines, and contact details before creating official offers.')">
            <i class="bi bi-envelope-check me-1"></i>Generate Offer Letters
        </button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col"><input type="checkbox" id="selectAllChk" onchange="document.querySelectorAll('input[name=\'applicant_ids[]\']').forEach(c=>c.checked=this.checked)" title="Select all"></th>
                        <th scope="col">Rank</th>
                        <th scope="col">Name</th>
                        <th scope="col">Application #</th>
                        @foreach($steps as $step)
                        <th scope="col" class="text-center small">{{ $step->name ?? $step->typeLabel }}</th>
                        @endforeach
                        <th scope="col" class="text-center">Academic</th>
                        <th scope="col" class="text-center">Composite</th>
                        <th scope="col" class="text-center">Decision</th>
                        @if($canApproveAdmission)
                        <th scope="col">Action</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                    <tr>
                        <td>
                            @if($canApproveAdmission)
                            <input aria-label="Select applicant {{ $entry->applicant->application_number ?? $entry->applicant_id }} for offer generation" type="checkbox" name="applicant_ids[]" value="{{ $entry->applicant_id }}" form="bulkOfferForm">
                            @endif
                        </td>
                        <td><strong>#{{ $entry->rank }}</strong></td>
                        <td>
                            <a href="{{ route('admission.applicants.show', $entry->applicant) }}">
                                {{ $entry->applicant->user->name ?? 'Applicant name not recorded' }}
                            </a>
                        </td>
                        <td class="small text-muted">{{ $entry->applicant->application_number ?? 'Application number not assigned' }}</td>
                        @foreach($steps as $stepId => $step)
                        <td class="text-center small">
                            @php($ss = ($entry->step_scores ?? [])[$stepId] ?? null)
                            @if($ss)
                                {{ number_format($ss['percentage'] ?? 0, 1) }}%
                            @else
                                <span class="text-muted">Score not recorded</span>
                            @endif
                        </td>
                        @endforeach
                        <td class="text-center">{{ $entry->academic_score !== null ? number_format($entry->academic_score, 1).'%' : 'Academic score pending' }}</td>
                        <td class="text-center fw-bold">{{ number_format($entry->composite_score, 2) }}</td>
                        <td class="text-center"><span class="{{ $entry->decisionBadge }}">{{ $entry->decisionLabel }}</span></td>
                        @if($canApproveAdmission)
                        <td>
                            <form method="POST" action="{{ route('admission.merit-list.decide', $entry) }}" class="d-flex gap-1 align-items-center">
                                @csrf
                                <select aria-label="Decision" name="decision" class="form-select form-select-sm" style="width:130px">
                                    <option value="selected" @selected($entry->decision=='selected')>Selected</option>
                                    <option value="waitlisted" @selected($entry->decision=='waitlisted')>Waitlisted</option>
                                    <option value="rejected" @selected($entry->decision=='rejected')>Rejected</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary">Save decision</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="20" class="text-center py-5">
                            <div class="fw-semibold text-dark mb-1">No merit-list entries match this view</div>
                            <div class="text-muted small mb-3">Check the selected batch and decision filters, then confirm that the merit list was generated after shortlisted applicants and assessment scores were available.</div>
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                @if($batchId || $decision)
                                    <a href="{{ route('admission.merit-list.show', $program) }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                                @endif
                                <a href="{{ route('admission.merit-list.index', $program) }}" class="btn btn-sm btn-primary">Review Merit Setup</a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $entries->withQueryString()->links() }}</div>

@if($canApproveAdmission)
</form>
@endif

<script>
function selectAll() {
    document.querySelectorAll('input[name="applicant_ids[]"]').forEach(c => c.checked = true);
}
</script>
@endsection
