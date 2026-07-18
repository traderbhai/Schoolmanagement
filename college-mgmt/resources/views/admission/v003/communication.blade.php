@extends('layouts.admin')
@section('title', 'Admission Communication Hub')
@section('content')
<div class="container-fluid py-4">
    <x-ui.page-header
        title="Communication Hub"
        subtitle="Create approved templates, monitor queued messages, and dispatch provider-backed admission communication from one controlled surface."
    />
    @unless($canManageCommunication)
        <div class="alert alert-warning py-2">Read-only view for your Admission scope. Template management and queued-message dispatch require Admission leadership approval.</div>
    @endunless
    <div class="alert alert-info border-0 shadow-sm small mb-3">
        <div class="fw-semibold mb-1">Communication safety sequence</div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-light border">1. Create template</span>
            <span class="badge text-bg-light border">2. Check consent and approval</span>
            <span class="badge text-bg-light border">3. Queue message</span>
            <span class="badge text-bg-light border">4. Dispatch through provider</span>
            <span class="badge text-bg-light border">5. Monitor delivery status</span>
        </div>
        <div class="text-muted mt-2">Use Bulk Communication for audience sends and Communication Safety to review opt-outs, quiet hours, blocked recipients, and delayed sends before dispatch.</div>
    </div>
    <div class="row g-4">
        <div class="col-lg-4">
            <form method="POST" action="{{ route('admission.communication.templates.store') }}" class="card" onsubmit="return confirm('Save this communication template for Admission use? Confirm channel, purpose, variables, approval readiness, and future automated/bulk-send usage before saving.')">
                @csrf
                <div class="card-header">
                    <div class="fw-semibold">Template</div>
                    <div class="small text-muted">Keep variables clear so counsellors can reuse the same message safely across leads and applicants.</div>
                </div>
                <div class="card-body vstack gap-3">
                    <input aria-label="Name" class="form-control" name="name" placeholder="Name" required>
                    <select aria-label="Channel" class="form-select" name="channel"><option>email</option><option>internal</option><option>sms</option><option>whatsapp</option></select>
                    <input aria-label="Purpose" class="form-control" name="purpose" placeholder="Purpose" value="general">
                    <input aria-label="Subject" class="form-control" name="subject" placeholder="Subject">
                    <textarea aria-label="Body" class="form-control" name="body" rows="5" required>Hello @{{ name }}, your @{{ program }} admission status is @{{ status }}.</textarea>
                    <button class="btn btn-primary" @disabled(! $canManageCommunication)>Save Template</button>
                </div>
            </form>
        </div>
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <div class="fw-semibold">Templates</div>
                    <div class="small text-muted">Templates should move through safety approval before bulk or automated sends.</div>
                </div>
                <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col">Name</th><th scope="col">Channel</th><th scope="col">Purpose</th></tr></thead><tbody>
                    @forelse($templates as $template)
                        <tr>
                            <td>{{ $template->name ?: 'Template name missing' }}</td>
                            <td>{{ strtoupper($template->channel ?: 'internal') }}</td>
                            <td>{{ $template->purpose ?: 'Purpose not classified' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-muted text-center py-4">
                                <div class="fw-semibold text-body mb-1">No communication templates are configured yet.</div>
                                <div class="small">Create an approved template before counsellors, reminders, automations, assessments, offers, or parent journeys can queue reusable messages.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody></table></div>
            </div>
            <form method="POST" action="{{ route('admission.communication.dispatch') }}" class="mb-3" onsubmit="return confirm('Dispatch all queued Admission messages through the configured providers? Confirm consent, quiet hours, approved templates, blocked recipients, and provider readiness before sending.')">@csrf<button class="btn btn-outline-success" @disabled(! $canManageCommunication)>Dispatch Queued Messages</button> <span class="small text-muted ms-2">Runs provider-ready queued messages after safety checks have already created the queue.</span></form>
            <div class="card">
                <div class="card-header">
                    <div class="fw-semibold">Recent Messages</div>
                    <div class="small text-muted">Use this table to confirm queued, sent, failed, delayed, or blocked delivery states.</div>
                </div>
                <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col">Channel</th><th scope="col">Provider</th><th scope="col">Status</th><th scope="col">Recipient</th></tr></thead><tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ strtoupper($log->channel ?: 'internal') }}</td>
                            <td>{{ $log->provider ?: 'Provider not selected' }}</td>
                            <td>{{ ucfirst($log->status ?: 'queued') }}</td>
                            <td>{{ $log->recipient ?: 'Recipient not recorded' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-muted text-center py-4">
                                <div class="fw-semibold text-body mb-1">No communication logs are visible in this scope yet.</div>
                                <div class="small mb-3">Messages appear here after a lead, applicant, reminder, automation, assessment, offer, or bulk-send workflow queues communication through the safety service.</div>
                                <div class="d-flex justify-content-center flex-wrap gap-2">
                                    <a href="{{ route('admission.bulk-communication.index') }}" class="btn btn-sm btn-outline-primary">Bulk Communication</a>
                                    <a href="{{ route('admission.communication-safety.index') }}" class="btn btn-sm btn-outline-primary">Communication Safety</a>
                                    <a href="{{ route('admission.reminders.index') }}" class="btn btn-sm btn-outline-secondary">Reminder Queue</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody></table></div>
            </div>
        </div>
    </div>
</div>
@endsection
