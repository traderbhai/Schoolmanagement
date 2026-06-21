@extends('layouts.admin')

@section('title', 'All Applicants - Admission CRM')

@push('styles')
<style>
    .admission-compact .card { border-radius: 6px; }
    .admission-compact .card-body { padding: .75rem; }
    .admission-compact .table > :not(caption) > * > * { padding: .45rem .6rem; }
    .admission-compact .sort-link { color:inherit; text-decoration:none; }
</style>
@endpush

@section('content')
@php
    $nextDirection = fn (string $field) => ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
    $sortIcon = fn (string $field) => $sort === $field ? ($direction === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-arrow-down-up';
    $sortUrl = fn (string $field) => request()->fullUrlWithQuery(['sort' => $field, 'direction' => $nextDirection($field)]);
    $filterSummary = collect([
        request('status') ? 'Status: ' . ucfirst(str_replace('_', ' ', request('status'))) : null,
        request('program_id') ? 'Program filtered' : null,
        request('batch_id') ? 'Batch filtered' : null,
        request('counsellor_id') ? 'Counsellor filtered' : null,
        request('search') ? 'Search: ' . request('search') : null,
        request('date_from') ? 'From: ' . request('date_from') : null,
        request('date_to') ? 'To: ' . request('date_to') : null,
    ])->filter()->implode(' | ') ?: 'All visible applicants';
@endphp

<div class="admission-compact">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-bold mb-1">All Applicants</h3>
            <div class="text-muted small">{{ $applicants->total() }} records after filters</div>
            <div class="small text-muted">Filter: {{ $filterSummary }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admission.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
            <a href="{{ route('admission.applicants.export-csv', request()->query()) }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Name / Email / App#" value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Program</label>
                    <select name="program_id" class="form-select form-select-sm">
                        <option value="">All Programs</option>
                        @foreach($programs as $p)
                            <option value="{{ $p->id }}" @selected(request('program_id') == $p->id)>{{ $p->abbreviation ?? $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        @foreach($statuses as $s)
                            <option value="{{ $s }}" @selected(request('status') == $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-1">Rows</label>
                    <select name="per_page" class="form-select form-select-sm">
                        @foreach([10,20,50,100] as $size)
                            <option value="{{ $size }}" @selected(request('per_page', 20) == $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-12 d-flex justify-content-end gap-1">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Apply</button>
                    <a href="{{ route('admission.applicants.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <form id="bulkForm" action="{{ route('admission.applicants.bulk-action') }}" method="POST">
        @csrf
        <div id="bulkBar" class="alert alert-warning d-none mb-2 py-2 d-flex align-items-center gap-3">
            <span class="fw-semibold"><span id="selectedCount">0</span> selected</span>
            <select name="action" class="form-select form-select-sm w-auto" required>
                <option value="">Bulk Action</option>
                <option value="under_review">Move to Under Review</option>
                <option value="shortlisted">Move to Shortlisted</option>
                <option value="rejected">Move to Rejected</option>
                <option value="withdrawn">Mark Withdrawn</option>
            </select>
            <button type="submit" class="btn btn-warning btn-sm">Apply</button>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="40"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                            <th>Name / Email</th>
                            <th><a class="sort-link" href="{{ $sortUrl('application_number') }}">App # <i class="bi {{ $sortIcon('application_number') }}"></i></a></th>
                            <th>Program</th>
                            <th><a class="sort-link" href="{{ $sortUrl('status') }}">Status <i class="bi {{ $sortIcon('status') }}"></i></a></th>
                            <th><a class="sort-link" href="{{ $sortUrl('applied_at') }}">Applied <i class="bi {{ $sortIcon('applied_at') }}"></i></a></th>
                            <th>Last Interaction</th>
                            <th>Next Follow-up</th>
                            <th>Complete</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($applicants as $applicant)
                        <tr>
                            <td><input type="checkbox" name="applicant_ids[]" value="{{ $applicant->id }}" class="form-check-input row-check"></td>
                            <td>
                                <div class="fw-semibold">{{ $applicant->user->name ?? 'Applicant name missing' }}</div>
                                <div class="text-muted small">{{ $applicant->user->email ?? 'Email not provided' }}</div>
                            </td>
                            <td class="font-monospace small">{{ $applicant->application_number }}</td>
                            <td class="small">{{ $applicant->program->abbreviation ?? $applicant->program->name ?? 'Program not assigned' }}</td>
                            <td><span class="{{ $applicant->status_badge }}">{{ $applicant->status_label }}</span></td>
                            <td class="small">{{ $applicant->applied_at ? $applicant->applied_at->format('d M Y') : '-' }}</td>
                            <td class="small">
                                @if($applicant->counsellingLogs->first())
                                    <span class="badge bg-secondary">{{ ucfirst(str_replace('_',' ',$applicant->counsellingLogs->first()->outcome)) }}</span>
                                    <div class="text-muted" style="font-size:0.75rem">{{ $applicant->counsellingLogs->first()->created_at->diffForHumans() }}</div>
                                @else
                                    <span class="text-muted">No interaction yet</span>
                                @endif
                            </td>
                            <td class="small">
                                @if($applicant->counsellingLogs->first()?->next_followup_date)
                                    <span class="badge bg-warning text-dark">{{ $applicant->counsellingLogs->first()->next_followup_date->format('d M') }}</span>
                                @else
                                    <span class="text-muted">No follow-up set</span>
                                @endif
                            </td>
                            @php $pct = $completenessMap[$applicant->id] ?? 0; @endphp
                            <td style="min-width:80px">
                                <div class="d-flex align-items-center gap-1">
                                    <div class="progress flex-grow-1" style="height:6px">
                                        <div class="progress-bar {{ $pct >= 80 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger') }}" style="width:{{ $pct }}%"></div>
                                    </div>
                                    <span class="small text-muted" style="white-space:nowrap">{{ $pct }}%</span>
                                </div>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admission.applicants.show', $applicant) }}" class="btn btn-sm btn-outline-primary py-0 px-2">Open</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="py-4">
                                <div class="text-center text-muted">
                                    <div class="fw-semibold text-dark mb-1">No applicants are visible in this list</div>
                                    <div class="small mb-3">
                                        Applicants appear here when they match your Admission role scope and the active filters above.
                                        Clear filters if you expected records, or open Leads to convert new prospects into applicants.
                                    </div>
                                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                                        <a href="{{ route('admission.applicants.index') }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                                        <a href="{{ route('admission.leads.index') }}" class="btn btn-sm btn-outline-primary">Open Leads</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
                <div class="small text-muted">Showing {{ $applicants->firstItem() ?? 0 }}-{{ $applicants->lastItem() ?? 0 }} of {{ $applicants->total() }}</div>
                {{ $applicants->links() }}
            </div>
        </div>
    </form>
</div>

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
