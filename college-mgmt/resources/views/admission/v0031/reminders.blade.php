@extends('layouts.admin')

@section('title', 'Admission Reminders')

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="fw-bold mb-1">Reminder And Cadence Engine</h3><div class="text-muted small">Scheduled reminders create queued communication logs through the communication hub.</div></div>
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
@endsection
