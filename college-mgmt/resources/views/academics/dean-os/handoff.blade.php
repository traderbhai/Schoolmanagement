@extends('layouts.admin')
@section('title', 'Dean Handoff Oversight')

@section('content')
@php
    $filters = $filters ?? [];
    $recordTotal = method_exists($records, 'total') ? $records->total() : $records->count();
    $filterSummary = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->map(fn ($value, $key) => str($key)->headline().': '.$value)->join(' | ');
@endphp
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">Admission To Academics Handoff</h1><div class="small text-muted">Dean visibility into ready, blocked, returned, and completed handoffs.</div></div>@include('academics.dean-os.partials.nav')</div>
    <div class="row g-2 mb-3">@foreach(['pending_admission_completion','blocked','ready_for_academics','handed_off','returned_for_correction'] as $status)<div class="col-6 col-xl"><a href="{{ route('academics.dean-os.handoff', ['status'=>$status]) }}" class="card shadow-sm text-decoration-none"><div class="card-body py-2"><div class="small text-muted">{{ str_replace('_',' ', $status) }}</div><div class="h5 mb-0">{{ $counts[$status] ?? 0 }}</div></div></a></div>@endforeach</div>
    <div class="card shadow-sm"><div class="card-header py-2 d-flex flex-wrap justify-content-between gap-2"><div><div class="fw-semibold">Filtered Source List ({{ $recordTotal }})</div><div class="small text-muted">Visible filter summary: {{ $filterSummary ?: 'Showing all admission-to-academics handoff records.' }}</div></div><a href="{{ route('academics.dean-os.export', 'handoff_readiness') }}?{{ http_build_query($filters) }}" class="btn btn-sm btn-outline-secondary">Export current view</a></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col">Applicant</th><th scope="col">Status</th><th scope="col">Blockers</th><th scope="col">Notes</th><th scope="col">Updated</th></tr></thead><tbody>
        @forelse($records as $record)
            <tr><td><div class="fw-semibold">{{ $record->applicant_name ?? $record->application_number ?? 'Unassigned applicant' }}</div><div class="small text-muted">{{ $record->application_number ?: 'No application number' }}</div></td><td><span class="badge text-bg-light">{{ str_replace('_',' ', $record->status) }}</span></td><td class="small">@foreach((json_decode($record->blockers ?? '[]', true) ?: []) as $blocker)<span class="badge text-bg-warning me-1">{{ $blocker }}</span>@endforeach</td><td class="small text-muted">{{ $record->handoff_notes }}</td><td class="small">{{ $record->updated_at }}</td></tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No handoff records match this filter.</td></tr>
        @endforelse
    </tbody></table></div></div>
    @if(method_exists($records, 'links'))<div class="mt-2">{{ $records->links() }}</div>@endif
</div>
@endsection
