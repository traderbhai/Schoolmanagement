@extends('layouts.admin')

@section('title', 'Merit List — ' . $program->name)

@section('content')
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
                <a href="{{ route('admission.merit-list.category-report', $program) }}" class="btn btn-outline-info btn-sm">
                    <i class="bi bi-bar-chart me-1"></i>Category Report
                </a>
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
        <div class="col-sm-2">
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
            <label class="form-label small">Quota</label>
            <select name="quota_type" class="form-select form-select-sm">
                <option value="">All Quotas</option>
                <option value="open" @selected(request('quota_type') == 'open')>Open</option>
                <option value="category" @selected(request('quota_type') == 'category')>Category</option>
                <option value="pwd" @selected(request('quota_type') == 'pwd')>PWD</option>
                <option value="nri" @selected(request('quota_type') == 'nri')>NRI</option>
                <option value="management" @selected(request('quota_type') == 'management')>Management</option>
            </select>
        </div>
        <div class="col-sm-2">
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        </div>
    </div>
</form>

{{-- Bulk Decide (head only) --}}
@if(auth()->user()->hasRole('admission_head') || auth()->user()->hasRole('admin'))
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

{{-- Seat Matrix Summary --}}
@if(isset($seatMatrix) && $seatMatrix)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-bold"><i class="bi bi-grid-3x3-gap me-2"></i>Seat Matrix — Category Fill Status</h6>
    </div>
    <div class="card-body">
        <div class="row g-2 text-center">
            @foreach(['general' => 'General','obc' => 'OBC','obc_nc' => 'OBC-NC','sc' => 'SC','st' => 'ST','ews' => 'EWS','pwd' => 'PWD*','nri' => 'NRI*','management_quota' => 'Mgmt'] as $cat => $lbl)
            @php $col = $cat . '_seats'; $seats = $seatMatrix->$col; @endphp
            <div class="col-6 col-sm-4 col-md-2">
                <div class="border rounded p-2">
                    <div class="fw-bold text-primary">{{ $seats }}</div>
                    <small class="text-muted">{{ $lbl }}</small>
                </div>
            </div>
            @endforeach
            <div class="col-6 col-sm-4 col-md-2">
                <div class="border rounded p-2 bg-light">
                    <div class="fw-bold">{{ $seatMatrix->total_seats }}</div>
                    <small class="text-muted">Total</small>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Merit List Table --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Rank</th>
                        <th>Name</th>
                        <th>Application #</th>
                        <th>Category</th>
                        <th class="text-center">Cat. Rank</th>
                        <th class="text-center">Quota</th>
                        @foreach($steps as $step)
                        <th class="text-center small">{{ $step->name ?? $step->typeLabel }}</th>
                        @endforeach
                        <th class="text-center">Academic</th>
                        <th class="text-center">Composite</th>
                        <th class="text-center">Decision</th>
                        @if(auth()->user()->hasRole('admission_head') || auth()->user()->hasRole('admin'))
                        <th>Action</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                    <tr>
                        <td><strong>#{{ $entry->rank }}</strong></td>
                        <td>
                            <a href="{{ route('admission.applicants.show', $entry->applicant) }}">
                                {{ $entry->applicant->user->name ?? 'N/A' }}
                            </a>
                        </td>
                        <td class="small text-muted">{{ $entry->applicant->application_number }}</td>
                        <td class="small">
                            @if($entry->applicant->category)
                                <span class="badge bg-light text-dark border">{{ $entry->applicant->category_label }}</span>
                            @else —
                            @endif
                        </td>
                        <td class="text-center small">
                            @if($entry->category_rank) #{{ $entry->category_rank }} @else — @endif
                        </td>
                        <td class="text-center small">
                            @php $quoteBadge = match($entry->quota_type) {
                                'open' => 'bg-primary', 'category' => 'bg-info text-dark',
                                'pwd' => 'bg-warning text-dark', 'nri' => 'bg-purple text-white',
                                'management' => 'bg-secondary', default => 'bg-light text-dark'
                            }; @endphp
                            @if($entry->quota_type)
                                <span class="badge {{ $quoteBadge }}">{{ ucfirst($entry->quota_type) }}</span>
                                @if($entry->is_supernumerary) <i class="bi bi-asterisk text-muted" title="Supernumerary"></i> @endif
                            @else — @endif
                        </td>
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
                        @if(auth()->user()->hasRole('admission_head') || auth()->user()->hasRole('admin'))
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
@endsection
