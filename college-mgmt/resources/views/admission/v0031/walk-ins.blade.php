@extends('layouts.admin')

@section('title', 'Walk-ins')

@section('content')
@php
    $nextDirection = fn (string $field) => ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
    $sortIcon = fn (string $field) => $sort === $field ? ($direction === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-arrow-down-up';
    $sortUrl = fn (string $field) => request()->fullUrlWithQuery(['sort' => $field, 'direction' => $nextDirection($field)]);
    $filterSummary = collect([
        request('search') ? 'Search: '.request('search') : null,
        request('status') ? 'Status: '.ucfirst(request('status')) : null,
        request('program_id') ? 'Program filtered' : null,
        'Sort: '.str_replace('_', ' ', $sort).' '.$direction,
    ])->filter()->implode(' | ');
@endphp
<div class="admission-compact">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="fw-bold mb-1">Walk-in And Campus Visit Desk</h3><div class="text-muted small">{{ $walkIns->total() }} visits after filters. Create walk-in leads, assign counsellors, and track visit conversion.</div></div>
</div>
<div class="alert alert-info small">
    <strong>Walk-in workflow:</strong> record the campus visit, assign the counsellor, schedule the next follow-up, then convert to a lead when the enquiry is qualified.
    <span class="d-block mt-1">Visible filter summary: {{ $filterSummary }}</span>
</div>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small mb-1">Search</label><input name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Name, phone, email"></div>
            <div class="col-md-2"><label class="form-label small mb-1">Status</label><select name="status" class="form-select form-select-sm"><option value="">All Status</option>@foreach(['open','converted','closed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label small mb-1">Program</label><select name="program_id" class="form-select form-select-sm"><option value="">All Programs</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>{{ $program->abbreviation ?? $program->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label small mb-1">Rows</label><select name="per_page" class="form-select form-select-sm">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @selected(request('per_page', 25) == $size)>{{ $size }}</option>@endforeach</select></div>
            <div class="col-md-2 d-flex gap-1"><button class="btn btn-primary btn-sm flex-fill">Apply</button><a href="{{ route('admission.walk-ins.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a></div>
        </form>
    </div>
</div>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><a class="text-decoration-none text-reset" href="{{ $sortUrl('visitor_name') }}">Visitor <i class="bi {{ $sortIcon('visitor_name') }}"></i></a></th>
                            <th>Program</th>
                            <th>Counsellor</th>
                            <th><a class="text-decoration-none text-reset" href="{{ $sortUrl('visited_at') }}">Visit <i class="bi {{ $sortIcon('visited_at') }}"></i></a></th>
                            <th><a class="text-decoration-none text-reset" href="{{ $sortUrl('status') }}">Status <i class="bi {{ $sortIcon('status') }}"></i></a></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($walkIns as $walkIn)
                        <tr>
                            <td><div class="fw-semibold">{{ $walkIn->visitor_name }}</div><div class="small text-muted">{{ $walkIn->visitor_phone ?: 'Phone not captured' }}</div></td>
                            <td>{{ $walkIn->program->name ?? 'Program not selected' }}</td>
                            <td>{{ $walkIn->counsellor->name ?? 'Unassigned' }}</td>
                            <td>{{ optional($walkIn->visited_at)->format('d M Y H:i') ?? 'Visit time not recorded' }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($walkIn->status) }}</span></td>
                            <td>
                                @if(!$walkIn->lead_id)
                                <form method="POST" action="{{ route('admission.walk-ins.convert', $walkIn) }}">@csrf<button class="btn btn-sm btn-outline-success">Convert</button></form>
                                @else
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admission.leads.show', $walkIn->lead_id) }}">Lead</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if($walkIns->isEmpty())
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <div class="fw-semibold text-dark">No walk-in visits match the current scope or filters.</div>
                                <div class="small">Clear filters, record a quick walk-in, or confirm that the visitor is assigned to a counsellor visible to your Admission role.</div>
                                <a href="{{ route('admission.walk-ins.index') }}" class="btn btn-sm btn-outline-primary mt-2">Clear Filters</a>
                            </td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
                <div class="small text-muted">Showing {{ $walkIns->firstItem() ?? 0 }}-{{ $walkIns->lastItem() ?? 0 }} of {{ $walkIns->total() }}</div>
                {{ $walkIns->links() }}
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-bold">Quick Walk-in</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admission.walk-ins.store') }}" class="vstack gap-2">
                    @csrf
                    <input class="form-control form-control-sm" name="visitor_name" placeholder="Visitor name" required>
                    <input class="form-control form-control-sm" name="visitor_phone" placeholder="Phone">
                    <input class="form-control form-control-sm" name="visitor_email" placeholder="Email">
                    <input class="form-control form-control-sm" name="guardian_name" placeholder="Guardian name">
                    <select class="form-select form-select-sm" name="program_id"><option value="">Program</option>@foreach($programs as $program)<option value="{{ $program->id }}">{{ $program->name }}</option>@endforeach</select>
                    <select class="form-select form-select-sm" name="assigned_counsellor_id"><option value="">Counsellor</option>@foreach($counsellors as $counsellor)<option value="{{ $counsellor->id }}">{{ $counsellor->name }}</option>@endforeach</select>
                    <input class="form-control form-control-sm" name="purpose" value="admission_enquiry" required>
                    <input class="form-control form-control-sm" type="datetime-local" name="next_followup_at">
                    <textarea class="form-control form-control-sm" name="notes" rows="2" placeholder="Visit notes"></textarea>
                    <button class="btn btn-primary btn-sm">Record Walk-in</button>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent">
                <div class="fw-bold">Conversion Report</div>
                <div class="small text-muted">Uses the same Admission visibility scope and current program/search filters.</div>
            </div>
            <div class="list-group list-group-flush">
                @foreach($report as $row)
                    <div class="list-group-item d-flex justify-content-between"><span>{{ $row['counsellor'] }}</span><strong>{{ $row['conversion_pct'] }}%</strong></div>
                @endforeach
                @if($report->isEmpty())
                    <div class="list-group-item text-muted small">No scoped walk-in visits are available for conversion reporting yet.</div>
                @endif
            </div>
        </div>
    </div>
</div>
</div>
@endsection
