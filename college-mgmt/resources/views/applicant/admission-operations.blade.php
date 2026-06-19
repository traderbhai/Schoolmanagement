@extends('layouts.applicant')

@section('title', 'Admission Operations')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Admission Operations</h1>
            <div class="text-muted small">{{ $applicant->application_number }} · {{ $applicant->program?->name }} · {{ ucfirst(str_replace('_', ' ', $applicant->status)) }}</div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('applicant.checklist') }}">Checklist</a>
    </div>

    @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-warning py-2">{{ session('error') }}</div>@endif
    @unless($canRequestAssessmentChanges)
        <div class="alert alert-secondary py-2">
            Assessment reschedule requests are closed because this application is already in a final admission state.
        </div>
    @endunless

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-header py-2 fw-semibold">Assessment Slots</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>Slot</th><th>When</th><th>Venue / Link</th><th>Status</th><th>Reschedule</th></tr></thead>
                        <tbody>
                        @forelse($slots as $slot)
                            <tr>
                                <td>{{ $slot->slot_code }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($slot->starts_at)->format('d M, h:i A') }}</td>
                                <td>{{ $slot->venue ?: $slot->online_link ?: 'To be announced' }}</td>
                                <td><span class="badge text-bg-info">{{ str_replace('_', ' ', $slot->status) }}</span></td>
                                <td>
                                    @if($canRequestAssessmentChanges)
                                    <form method="POST" action="{{ route('applicant.admission-operations.reschedule') }}" class="d-flex gap-1">
                                        @csrf
                                        <input type="hidden" name="slot_assignment_id" value="{{ $slot->id }}">
                                        <select name="requested_slot_id" class="form-select form-select-sm" aria-label="Requested slot">
                                            <option value="">Staff suggested slot</option>
                                            @foreach($availableSlots as $available)
                                                <option value="{{ $available->id }}">{{ $available->slot_code }} · {{ \Illuminate\Support\Carbon::parse($available->starts_at)->format('d M h:i A') }}</option>
                                            @endforeach
                                        </select>
                                        <input name="reason" class="form-control form-control-sm" placeholder="Reason" required>
                                        <button class="btn btn-sm btn-primary">Send</button>
                                    </form>
                                    @else
                                        <span class="badge text-bg-secondary">Closed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No assessment slot assigned yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header py-2 fw-semibold">Submissions, Waitlist, Seat, Joining Kit</div>
                <div class="row g-0">
                    <div class="col-md-6 border-end p-3">
                        <h2 class="h6">Assessment Submissions</h2>
                        @forelse($submissions as $submission)
                            <div class="small mb-2">{{ ucfirst($submission->submission_type) }} · <span class="badge text-bg-secondary">{{ $submission->status }}</span></div>
                        @empty
                            <div class="text-muted small">No submission requirement is pending.</div>
                        @endforelse
                    </div>
                    <div class="col-md-6 p-3">
                        <h2 class="h6">Seat And Waitlist</h2>
                        @foreach($seatHolds as $hold)
                            <div class="small mb-2">Seat {{ $hold->status }} @if($hold->expires_at) · expires {{ \Illuminate\Support\Carbon::parse($hold->expires_at)->format('d M') }} @endif</div>
                        @endforeach
                        @foreach($waitlist as $entry)
                            <div class="small mb-2">Waitlist rank {{ $entry->rank }} · {{ $entry->status }}</div>
                        @endforeach
                        @if($seatHolds->isEmpty() && $waitlist->isEmpty())<div class="text-muted small">No seat or waitlist record yet.</div>@endif
                    </div>
                </div>
                <div class="table-responsive border-top">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Joining Task</th><th>Status</th><th>Due</th></tr></thead>
                        <tbody>
                        @forelse($joiningTasks as $task)
                            <tr><td>{{ $task->title }}</td><td>{{ $task->status }}</td><td>{{ $task->due_at ? \Illuminate\Support\Carbon::parse($task->due_at)->format('d M') : '-' }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">Joining kit will appear after offer acceptance.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header py-2 fw-semibold">Consent Preferences</div>
                <div class="card-body">
                    @foreach(['email','sms','whatsapp','call'] as $channel)
                        <form method="POST" action="{{ route('applicant.admission-operations.consent') }}" class="d-flex align-items-center gap-2 mb-2">
                            @csrf
                            <input type="hidden" name="channel" value="{{ $channel }}">
                            <label class="small text-uppercase flex-grow-1">{{ $channel }}</label>
                            <select name="status" class="form-select form-select-sm" aria-label="{{ $channel }} consent">
                                <option value="opt_in" @selected(($consents[$channel]->status ?? 'opt_in') === 'opt_in')>Opt in</option>
                                <option value="opt_out" @selected(($consents[$channel]->status ?? '') === 'opt_out')>Opt out</option>
                            </select>
                            <input name="reason" class="form-control form-control-sm" placeholder="Reason">
                            <button class="btn btn-sm btn-outline-primary">Save</button>
                        </form>
                    @endforeach
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header py-2 fw-semibold">Academics / PMC Handoff</div>
                <div class="card-body">
                    @if($handoff)
                        <div class="d-flex justify-content-between mb-2">
                            <span>Status</span>
                            <span class="badge text-bg-primary">{{ str_replace('_', ' ', $handoff->status) }}</span>
                        </div>
                        @foreach((json_decode($handoff->blockers ?? '[]', true) ?: []) as $blocker)
                            <div class="alert alert-warning py-2 small mb-2">{{ $blocker }}</div>
                        @endforeach
                        <div class="small text-muted">{{ $handoff->handoff_notes }}</div>
                    @else
                        <div class="text-muted small">Handoff will be generated when enrollment is ready.</div>
                    @endif
                    @foreach($deferrals as $deferral)
                        <div class="border-top mt-3 pt-2 small">Deferral: {{ $deferral->status }} · {{ $deferral->reason }}</div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
