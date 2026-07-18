@extends('layouts.admin')
@section('title', 'Communication Safety')

@section('content')
@php
    $subjectLabel = function ($type, $id) use ($subjectLabels) {
        return $subjectLabels[$type.':'.$id] ?? class_basename($type).' record unavailable';
    };
@endphp

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">Communication Safety</h3>
            <div class="text-muted small">Consent, quiet hours, template approvals, bulk-send preview, blocked queues, and sensitive audit coverage.</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('admission.communication.index') }}">Communication Hub</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admission.v039.exports','communication-safety') }}">Export Safety</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admission.v039.exports','consent') }}">Export Consent</a>
        </div>
    </div>
    <div class="alert alert-info border-0 shadow-sm small mb-3">
        <div class="fw-semibold mb-1">Safety gate sequence</div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-light border">1. Capture consent</span>
            <span class="badge text-bg-light border">2. Approve template</span>
            <span class="badge text-bg-light border">3. Preview audience</span>
            <span class="badge text-bg-light border">4. Block opt-outs and duplicates</span>
            <span class="badge text-bg-light border">5. Delay quiet-hour sends</span>
        </div>
        <div class="text-muted mt-2">Use this page before campaigns, reminders, automations, assessment messages, offers, and parent journeys so no send path bypasses consent or approval rules.</div>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Update Consent</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admission.consent-center.store') }}" class="row g-2" onsubmit="return confirm('Save this communication consent change? Confirm subject, channel, opt-in or opt-out status, source note, and downstream campaign/reminder impact before updating consent.')">
                        @csrf
                        <div class="col-12">
                            <select name="subject_key" class="form-select form-select-sm" aria-label="Lead or applicant" required>
                                <option value="">Select lead or applicant</option>
                                <optgroup label="Leads">
                                    @foreach($leads as $lead)
                                        <option value="lead:{{ $lead->id }}">{{ $lead->name }} - {{ $lead->email ?: $lead->phone }}</option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Applicants">
                                    @foreach($applicants as $applicant)
                                        <option value="applicant:{{ $applicant->id }}">{{ $applicant->user?->name ?: 'Applicant' }} - {{ $applicant->application_number }}</option>
                                    @endforeach
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-6">
                            <select name="channel" class="form-select form-select-sm" aria-label="Channel">
                                <option>sms</option>
                                <option>whatsapp</option>
                                <option>email</option>
                                <option>call</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <select name="status" class="form-select form-select-sm" aria-label="Consent status">
                                <option>opt_in</option>
                                <option>opt_out</option>
                            </select>
                        </div>
                        <div class="col-12"><input aria-label="Reason/source note" name="reason" class="form-control form-control-sm" placeholder="Reason/source note"></div>
                        <div class="col-12"><button type="submit" class="btn btn-sm btn-primary">Save communication consent</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Template Approval</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Template approval">
                        <thead><tr><th scope="col">Template</th><th scope="col">Channel</th><th aria-label="Actions" scope="col"></th></tr></thead>
                        <tbody>
                        @forelse($templates as $template)
                            <tr>
                                <td>{{ $template->name }}</td>
                                <td>{{ $template->channel }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admission.template-approvals.request', $template->id) }}" onsubmit="return confirm('Request approval for this communication template? Confirm channel, message text, consent usage, and campaign/reminder workflows before sending it for review.')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Request template approval</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted text-center py-3">No templates configured.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Bulk Safety Preview</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admission.communication-safety.preview') }}" class="row g-2" onsubmit="return confirm('Preview this communication audience? Confirm the template, audience type, consent rules, quiet-hour handling, duplicate blocking, and opt-out checks before calculating recipients.')">
                        @csrf
                        <div class="col-12">
                            <select name="template_id" class="form-select form-select-sm" aria-label="Template">
                                @foreach($templates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }} ({{ $template->channel }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <select name="audience" class="form-select form-select-sm" aria-label="Audience">
                                <option>leads</option>
                                <option>applicants</option>
                            </select>
                        </div>
                        <div class="col-12"><button type="submit" class="btn btn-sm btn-primary">Preview safe audience</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Approvals</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Template approvals">
                        <thead><tr><th scope="col">Template</th><th scope="col">Version</th><th scope="col">Status</th><th aria-label="Actions" scope="col"></th></tr></thead>
                        <tbody>
                        @forelse($approvals as $approval)
                            <tr>
                                <td>{{ $approval->template_name ?: 'Template record unavailable' }}</td>
                                <td>{{ $approval->version }}</td>
                                <td><span class="badge text-bg-secondary">{{ str($approval->status)->headline() }}</span></td>
                                <td>
                                    @if($approval->status !== 'approved')
                                        <form method="POST" action="{{ route('admission.template-approvals.approve', $approval->id) }}" onsubmit="return confirm('Approve this communication template version? Confirm wording, channel policy, consent rules, quiet-hour safety, and downstream automation/campaign usage before approval.')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success">Approve template version</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted text-center py-3">No template approvals pending.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Bulk Send Previews</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Bulk send previews">
                        <thead><tr><th scope="col">Channel</th><th scope="col">Audience</th><th scope="col">Blocked</th><th scope="col">Duplicates</th></tr></thead>
                        <tbody>
                        @forelse($previews as $preview)
                            <tr><td>{{ $preview->channel }}</td><td>{{ $preview->audience_count }}</td><td>{{ $preview->blocked_count }}</td><td>{{ $preview->duplicate_count }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="text-muted text-center py-3">No bulk preview has been run yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Consent Records</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Consent records">
                        <thead><tr><th scope="col">Subject</th><th scope="col">Channel</th><th scope="col">Status</th><th scope="col">Reason</th></tr></thead>
                        <tbody>
                        @forelse($consents as $consent)
                            <tr>
                                <td>{{ $subjectLabel($consent->subject_type, $consent->subject_id) }}</td>
                                <td>{{ $consent->channel }}</td>
                                <td><span class="badge text-bg-{{ $consent->status === 'opt_out' ? 'danger' : 'success' }}">{{ str($consent->status)->headline() }}</span></td>
                                <td>{{ \Illuminate\Support\Str::limit($consent->reason, 60) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted text-center py-3">No consent records captured.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Quiet Hours</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Quiet hours">
                        <thead><tr><th scope="col">Channel</th><th scope="col">Start</th><th scope="col">End</th><th scope="col">Emergency</th></tr></thead>
                        <tbody>
                        @forelse($quietHours as $rule)
                            <tr><td>{{ $rule->channel }}</td><td>{{ $rule->starts_at_time }}</td><td>{{ $rule->ends_at_time }}</td><td>{{ $rule->emergency_override_allowed ? 'Allowed' : 'Blocked' }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="text-muted text-center py-3">No active quiet-hour rules.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white fw-bold">Blocked And Delayed Send Queue</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0" aria-label="Blocked communications">
                <thead><tr><th scope="col">Subject</th><th scope="col">Channel</th><th scope="col">Recipient</th><th scope="col">Rule</th><th scope="col">Status</th><th scope="col">Scheduled</th></tr></thead>
                <tbody>
                @forelse($blocked as $item)
                    <tr>
                        <td>{{ $subjectLabel($item->subject_type, $item->subject_id) }}</td>
                        <td>{{ $item->channel }}</td>
                        <td>{{ $item->recipient }}</td>
                        <td>{{ $item->reason }}</td>
                        <td><span class="badge text-bg-secondary">{{ str($item->status)->headline() }}</span></td>
                        <td>{{ $item->scheduled_for }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted text-center">No blocked or delayed communications.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
