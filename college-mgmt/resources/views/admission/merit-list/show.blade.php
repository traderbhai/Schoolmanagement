@extends('layouts.admin')

@section('title', 'Merit List — ' . $program->name)

@section('content')
@php($canApproveAdmission = app(\App\Services\DepartmentHierarchyService::class)->canApproveAdmission(auth()->user()))
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-list-ol me-2"></i>{{ $program->name }} — Merit List</h3>
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

{{-- Filters --}}
<form method="GET" class="mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-sm-3">
            <label class="form-label small">Batch</label>
            <select name="batch_id" class="form-select form-select-sm">
                <option value="">All Batches</option>
                @foreach($batches as $b)
                    <option value="{{ $b->id }}" @selected($batchId == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-3">
            <label class="form-label small">Decision</label>
            <select name="decision" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="pending" @selected($decision == 'pending')>Pending</option>
                <option value="selected" @selected($decision == 'selected')>Selected</option>
                <option value="waitlisted" @selected($decision == 'waitlisted')>Waitlisted</option>
                <option value="rejected" @selected($decision == 'rejected')>Rejected</option>
            </select>
        </div>
        <div class="col-sm-2">
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        </div>
    </div>
</form>

{{-- Bulk Decide (head only) --}}
@if($canApproveAdmission)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('admission.merit-list.bulk-decide', $program) }}" class="row g-2 align-items-end" onsubmit="return confirm('Apply bulk decisions?')">
            @csrf
            @if($batchId) <input type="hidden" name="batch_id" value="{{ $batchId }}"> @endif
            <div class="col-sm-3">
                <label class="form-label small fw-semibold">Accept Top N</label>
                <input type="number" name="accept_top" class="form-control form-control-sm" min="1" value="10" required>
            </div>
            <div class="col-sm-3">
                <label class="form-label small fw-semibold">Waitlist Next M</label>
                <input type="number" name="waitlist_next" class="form-control form-control-sm" min="0" value="5">
            </div>
            <div class="col-sm-3">
                <button type="submit" class="btn btn-sm btn-warning">
                    <i class="bi bi-people-fill me-1"></i>Apply Bulk Decision
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Bulk Generate Offer Letters --}}
@if($canApproveAdmission)
<form method="POST" action="{{ route('admission.offer-letters.bulk-generate') }}" id="bulkOfferForm">
    @csrf
    <input type="hidden" name="program_id" value="{{ $program->id }}">
    <div class="d-flex gap-2 mb-3">
        <button type="button" onclick="selectAll()" class="btn btn-outline-secondary btn-sm">Select All</button>
        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Generate offer letters for selected applicants?')">
            <i class="bi bi-envelope-check me-1"></i>Generate Offer Letters
        </button>
    </div>
@endif

{{-- Merit List Table --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th><input type="checkbox" id="selectAllChk" onchange="document.querySelectorAll('input[name=\'applicant_ids[]\']').forEach(c=>c.checked=this.checked)" title="Select all"></th>
                        <th>Rank</th>
                        <th>Name</th>
                        <th>Application #</th>
                        @foreach($steps as $step)
                        <th class="text-center small">{{ $step->name ?? $step->typeLabel }}</th>
                        @endforeach
                        <th class="text-center">Academic</th>
                        <th class="text-center">Composite</th>
                        <th class="text-center">Decision</th>
                        @if($canApproveAdmission)
                        <th>Action</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                    <tr>
                        <td>
                            @if($canApproveAdmission)
                            <input type="checkbox" name="applicant_ids[]" value="{{ $entry->applicant_id }}" form="bulkOfferForm">
                            @endif
                        </td>
                        <td><strong>#{{ $entry->rank }}</strong></td>
                        <td>
                            <a href="{{ route('admission.applicants.show', $entry->applicant) }}">
                                {{ $entry->applicant->user->name ?? 'N/A' }}
                            </a>
                        </td>
                        <td class="small text-muted">{{ $entry->applicant->application_number }}</td>
                        @foreach($steps as $stepId => $step)
                        <td class="text-center small">
                            @php $ss = ($entry->step_scores ?? [])[$stepId] ?? null; @endphp
                            @if($ss)
                                {{ number_format($ss['percentage'] ?? 0, 1) }}%
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        @endforeach
                        <td class="text-center">{{ $entry->academic_score !== null ? number_format($entry->academic_score, 1).'%' : '—' }}</td>
                        <td class="text-center fw-bold">{{ number_format($entry->composite_score, 2) }}</td>
                        <td class="text-center"><span class="{{ $entry->decisionBadge }}">{{ $entry->decisionLabel }}</span></td>
                        @if($canApproveAdmission)
                        <td>
                            <form method="POST" action="{{ route('admission.merit-list.decide', $entry) }}" class="d-flex gap-1 align-items-center">
                                @csrf
                                <select name="decision" class="form-select form-select-sm" style="width:130px">
                                    <option value="selected" @selected($entry->decision=='selected')>Selected</option>
                                    <option value="waitlisted" @selected($entry->decision=='waitlisted')>Waitlisted</option>
                                    <option value="rejected" @selected($entry->decision=='rejected')>Rejected</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="20" class="text-center text-muted py-4">No entries found.</td></tr>
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
