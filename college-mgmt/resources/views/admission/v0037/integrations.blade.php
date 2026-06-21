@extends('layouts.admin')

@section('title', 'Admission Integrations')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">Admission Integrations</h3>
            <div class="text-muted small">Check sandbox/live provider readiness for SMS, WhatsApp, dialer, video assessment, and signature workflows.</div>
        </div>
        <a class="btn btn-outline-success btn-sm" href="{{ route('admission.v037.exports', 'route-access') }}">Export Audit</a>
    </div>

    <div class="alert alert-info py-2 small mb-3">
        <strong>Integration workflow:</strong> confirm provider status, run a sandbox test before enabling live credentials, watch webhook receipts, then retry only failed deliveries after reviewing the failure reason.
    </div>

    <div class="row g-2 mb-3">
        @foreach(['sms', 'whatsapp', 'dialer', 'video', 'signature'] as $channel)
            <div class="col-6 col-lg">
                <form method="POST" action="{{ route('admission.integrations.test') }}" class="card border-0 shadow-sm h-100">
                    @csrf
                    <input type="hidden" name="channel" value="{{ $channel }}">
                    <div class="card-body py-2">
                        <div class="small text-muted">{{ strtoupper($channel) }}</div>
                        <button class="btn btn-sm btn-primary mt-1">Sandbox Test</button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Provider Status</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" aria-label="Provider status">
                        <thead class="table-light">
                            <tr>
                                <th>Channel</th>
                                <th>Provider</th>
                                <th>Mode</th>
                                <th>Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($providers as $provider)
                                <tr>
                                    <td>{{ strtoupper($provider->channel) }}</td>
                                    <td>{{ $provider->provider_name ?: 'Provider not configured' }}</td>
                                    <td>{{ $provider->sandbox_mode ? 'Sandbox' : 'Live' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $provider->is_active ? 'success' : 'secondary' }}">{{ $provider->is_active ? 'Active' : 'Inactive' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <div class="fw-semibold text-dark">No integration providers are configured</div>
                                        <div class="small">Seed or configure sandbox providers before testing admission communication, dialer, video, or signature workflows.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Webhook Events</span>
                    <span class="small text-muted">{{ $webhooks->count() }} recent</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" aria-label="Webhook events">
                        <thead class="table-light">
                            <tr>
                                <th>Provider</th>
                                <th>Event</th>
                                <th>Status</th>
                                <th>When</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($webhooks as $event)
                                <tr>
                                    <td>{{ $event->provider_name ?: 'Provider pending' }}</td>
                                    <td>{{ $event->event_type ?: 'Event type pending' }}</td>
                                    <td>{{ ucwords(str_replace('_', ' ', $event->status ?: 'received')) }}</td>
                                    <td>{{ $event->created_at?->diffForHumans() ?? 'Time not captured' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <div class="fw-semibold text-dark">No provider webhook events are recorded yet</div>
                                        <div class="small">Webhook receipts appear after sandbox tests or live providers send delivery, call, meeting, or signature status updates.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold">Failed Delivery Retry Queue</span>
            <span class="small text-muted">{{ $failed->count() }} failed</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" aria-label="Failed communication deliveries">
                <thead class="table-light">
                    <tr>
                        <th>Channel</th>
                        <th>Provider</th>
                        <th>Recipient</th>
                        <th>Reason</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($failed as $log)
                        <tr>
                            <td>{{ strtoupper($log->channel) }}</td>
                            <td>{{ $log->provider ?: 'Provider pending' }}</td>
                            <td>{{ $log->recipient ?: 'Recipient not captured' }}</td>
                            <td>{{ $log->failure_reason ?: 'Failure reason not returned' }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admission.integrations.retry', $log) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary">Retry</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <div class="fw-semibold text-dark">No failed provider deliveries need retry</div>
                                <div class="small">Failed SMS, WhatsApp, dialer, video, or signature attempts appear here after dispatch returns a retryable error.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
