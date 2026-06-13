@extends('layouts.admin')

@section('title', 'Admission Reminders')

@push('styles')
<style>
    .admission-compact .card { border-radius: 6px; }
    .admission-compact .card-body { padding: .75rem; }
    .admission-compact .table > :not(caption) > * > * { padding: .45rem .6rem; }
</style>
@endpush

@section('content')
<div class="admission-compact">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="fw-bold mb-1">Reminder And Cadence Engine</h3><div class="text-muted small">{{ $reminders->total() }} reminders after filters. Scheduled reminders create queued communication logs.</div></div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small mb-1">Status</label><select name="status" class="form-select form-select-sm"><option value="">All Status</option>@foreach(['scheduled','queued','paused','escalated'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label small mb-1">Reason</label><input name="reason" value="{{ request('reason') }}" class="form-control form-control-sm" placeholder="document_blocker"></div>
            <div class="col-md-2"><label class="form-label small mb-1">Due Date</label><input type="date" name="date" value="{{ request('date') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label small mb-1">Rows</label><select name="per_page" class="form-select form-select-sm">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @selected(request('per_page', 25) == $size)>{{ $size }}</option>@endforeach</select></div>
            <div class="col-md-2 d-flex gap-1"><button class="btn btn-primary btn-sm flex-fill">Apply</button><a href="{{ route('admission.reminders.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a></div>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Reminder Queue</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Target</th><th>Reason</th><th>Channel</th><th>Due</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    @foreach($reminders as $reminder)
                        <tr>
                            <td>{{ class_basename($reminder->subject_type) }} #{{ $reminder->subject_id }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $reminder->reason)) }}</td>
                            <td>{{ strtoupper($reminder->channel) }}</td>
                            <td>{{ optional($reminder->due_at)->format('d M Y H:i') }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($reminder->status) }}</span></td>
                            <td class="d-flex gap-1 flex-wrap">
                                <form method="POST" action="{{ route('admission.reminders.send', $reminder) }}">@csrf<button class="btn btn-sm btn-outline-success">Send</button></form>
                                <form method="POST" action="{{ route('admission.reminders.complete', $reminder) }}">@csrf<button class="btn btn-sm btn-outline-primary">Done</button></form>
                                <form method="POST" action="{{ route('admission.reminders.pause', $reminder) }}">@csrf<button class="btn btn-sm btn-outline-warning">Pause</button></form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
                <div class="small text-muted">Showing {{ $reminders->firstItem() ?? 0 }}-{{ $reminders->lastItem() ?? 0 }} of {{ $reminders->total() }}</div>
                {{ $reminders->links() }}
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-bold">Create Cadence Rule</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admission.reminders.cadence') }}" class="vstack gap-2">
                    @csrf
                    <input class="form-control form-control-sm" name="name" placeholder="Rule name" required>
                    <select class="form-select form-select-sm" name="target_type"><option value="lead">Lead</option><option value="applicant">Applicant</option></select>
                    <input class="form-control form-control-sm" name="reason" value="no_response_follow_up" required>
                    <select class="form-select form-select-sm" name="channel"><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option><option value="internal">Internal</option></select>
                    <select class="form-select form-select-sm" name="template_id"><option value="">Template</option>@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach</select>
                    <div class="row g-2"><div class="col"><input class="form-control form-control-sm" name="initial_delay_hours" type="number" value="24"></div><div class="col"><input class="form-control form-control-sm" name="interval_hours" type="number" value="24"></div></div>
                    <button class="btn btn-primary btn-sm">Save Cadence</button>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Active Cadences</div>
            <div class="list-group list-group-flush">
                @foreach($cadenceRules as $rule)
                    <div class="list-group-item"><div class="fw-semibold">{{ $rule->name }}</div><div class="small text-muted">{{ $rule->target_type }} - {{ $rule->reason }} - {{ $rule->channel }}</div></div>
                @endforeach
            </div>
        </div>
    </div>
</div>
</div>
@endsection
