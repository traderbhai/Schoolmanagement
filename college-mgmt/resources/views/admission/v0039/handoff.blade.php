@extends('layouts.admin')

@section('title', 'Admission Handoff')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Admission To Academics / PMC Handoff</h1>
            <div class="text-muted small">Final readiness queue for student profile, roll number, documents, fee clearance, joining kit, and correction returns.</div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('admission.v039.exports', ['type' => 'handoff'] + request()->query()) }}">Export Current View</a>
    </div>

    @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif

    <div class="row g-2 mb-3">
        @foreach(['pending_admission_completion','blocked','ready_for_academics','handed_off','returned_for_correction'] as $state)
            <div class="col-6 col-md">
                <a href="{{ route('admission.handoff.index', ['status' => $state]) }}" class="card text-decoration-none shadow-sm">
                    <div class="card-body py-2">
                        <div class="small text-muted">{{ str_replace('_', ' ', ucfirst($state)) }}</div>
                        <div class="h5 mb-0">{{ $counts[$state] ?? 0 }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <form class="card card-body py-2 mb-3" method="GET" action="{{ route('admission.handoff.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small">Search</label>
                <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="Applicant name or application number">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All statuses</option>
                    @foreach(['pending_admission_completion','blocked','ready_for_academics','handed_off','returned_for_correction'] as $state)
                        <option value="{{ $state }}" @selected($status === $state)>{{ str_replace('_', ' ', ucfirst($state)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Apply</button></div>
            <div class="col-md-3 small text-muted">Filters: {{ $q ? 'search='.$q.'; ' : '' }}{{ $status ? 'status='.$status : 'all records' }}</div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Applicant</th><th>Status</th><th>Blockers</th><th>Documents</th><th>Fees</th><th>Joining Kit</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($records as $record)
                    @php
                        $blockers = json_decode($record->blockers ?? '[]', true) ?: [];
                        $docs = json_decode($record->verified_document_summary ?? '{}', true) ?: [];
                        $fees = json_decode($record->fee_clearance_summary ?? '{}', true) ?: [];
                        $kit = json_decode($record->joining_kit_summary ?? '{}', true) ?: [];
                    @endphp
                    <tr>
                        <td><div class="fw-semibold">{{ $record->applicant_name ?: 'Applicant '.$record->applicant_id }}</div><div class="small text-muted">{{ $record->application_number }}</div></td>
                        <td><span class="badge text-bg-primary">{{ str_replace('_', ' ', $record->status) }}</span></td>
                        <td class="small">@forelse($blockers as $blocker)<div>{{ $blocker }}</div>@empty<span class="text-success">None</span>@endforelse</td>
                        <td class="small">{{ $docs['verified_mandatory'] ?? 0 }}/{{ $docs['mandatory_total'] ?? 0 }}</td>
                        <td class="small">{{ $fees['verified_payments'] ?? 0 }} verified</td>
                        <td class="small">{{ $kit['completed'] ?? 0 }}/{{ $kit['total'] ?? 0 }}</td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <form method="POST" action="{{ route('admission.handoff.refresh', $record->applicant_id) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Refresh</button></form>
                                <form method="POST" action="{{ route('admission.handoff.mark-handed-off', $record->id) }}">@csrf<button class="btn btn-sm btn-success">Hand off</button></form>
                                <form method="POST" action="{{ route('admission.handoff.return', $record->id) }}" class="d-flex gap-1">@csrf<input name="reason" class="form-control form-control-sm" placeholder="Correction reason" required><button class="btn btn-sm btn-warning">Return</button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">No handoff records match the current filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer py-2">{{ $records->links() }}</div>
    </div>
</div>
@endsection
