@extends('layouts.admin')
@section('title', 'Offer And Seat Control')

@section('content')
@php
    $candidateLabel = function ($applicant) {
        $name = $applicant->user?->name ?: 'Applicant';
        $program = $applicant->program?->name ? ' - '.$applicant->program->name : '';
        return $name.' ('.$applicant->application_number.')'.$program;
    };

    $rowApplicantLabel = function ($row) {
        $name = $row->applicant_name ?: 'Applicant';
        $number = $row->applicant_application_number ?: 'No application number';
        return $name.' ('.$number.')';
    };
@endphp

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">Offer And Seat Control</h3>
            <div class="text-muted small">Offer rounds, waitlist movement, seat holds, deferrals, and joining-kit readiness.</div>
            @unless($canManageSeatControl)
                <div class="small text-warning">Read-only view for your Admission scope. Offer, seat, waitlist, and deferral changes require Admission leadership approval.</div>
            @endunless
        </div>
        <div class="d-flex gap-2">
            @if($programs->isNotEmpty())
                <a class="btn btn-sm btn-outline-primary" href="{{ route('admission.offer-letters.index', $programs->first()) }}">Offer Letters</a>
            @endif
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admission.v039.exports','offer-seat-control') }}">Export Current View</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Create Offer Round</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admission.offer-rounds.store') }}" class="row g-2" onsubmit="return confirm('Create this offer round for the selected program and batch?')">
                        @csrf
                        <div class="col-6">
                            <select name="program_id" class="form-select form-select-sm" aria-label="Program">
                                @foreach($programs as $program)
                                    <option value="{{ $program->id }}">{{ $program->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <select name="batch_id" class="form-select form-select-sm" aria-label="Batch">
                                @foreach($batches as $batch)
                                    <option value="{{ $batch->id }}">{{ $batch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-4"><input name="round_number" type="number" value="1" class="form-control form-control-sm" aria-label="Round number"></div>
                        <div class="col-8"><input name="name" value="Round {{ now()->format('M d') }}" class="form-control form-control-sm" aria-label="Round name"></div>
                        <div class="col-12"><input name="offer_valid_until" type="datetime-local" value="{{ now()->addDays(7)->format('Y-m-d\TH:i') }}" class="form-control form-control-sm" aria-label="Offer valid until"></div>
                        <div class="col-12"><button class="btn btn-sm btn-primary" @disabled(! $canManageSeatControl)>Create Round</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Offer Rounds</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Offer rounds">
                        <thead><tr><th>Round</th><th>Status</th><th>Valid Until</th><th></th></tr></thead>
                        <tbody>
                        @forelse($rounds as $round)
                            <tr>
                                <td>{{ $round->name }}</td>
                                <td><span class="badge text-bg-secondary">{{ str($round->status)->headline() }}</span></td>
                                <td>{{ $round->offer_valid_until }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admission.offer-rounds.publish', $round->id) }}" onsubmit="return confirm('Publish this offer round and create seat holds for eligible selected applicants?')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success" @disabled(! $canManageSeatControl)>Publish</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted text-center py-3">No offer rounds created yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white">{{ $rounds->links() }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Waitlist</div>
                <div class="card-body py-2">
                    <form method="POST" action="{{ route('admission.waitlist.store') }}" class="row g-1" onsubmit="return confirm('Add this applicant to the waitlist with the selected rank?')">
                        @csrf
                        <div class="col-12">
                            <select name="applicant_id" class="form-select form-select-sm" aria-label="Waitlist applicant" required>
                                <option value="">Select applicant</option>
                                @foreach($selectedApplicants as $applicant)
                                    <option value="{{ $applicant->id }}">{{ $candidateLabel($applicant) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-4"><input name="rank" type="number" class="form-control form-control-sm" value="1" min="1" aria-label="Waitlist rank"></div>
                        <div class="col-8"><button class="btn btn-sm btn-primary w-100" @disabled(! $canManageSeatControl)>Add To Waitlist</button></div>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Waitlist entries">
                        <thead><tr><th>Rank</th><th>Applicant</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($waitlist as $entry)
                            <tr>
                                <td>#{{ $entry->rank }}</td>
                                <td>{{ $rowApplicantLabel($entry) }}</td>
                                <td><span class="badge text-bg-warning">{{ str($entry->status)->headline() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted text-center py-3">No waitlist entries.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Seat Holds</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Seat holds">
                        <thead><tr><th>Applicant</th><th>Status</th><th>Expires</th><th></th></tr></thead>
                        <tbody>
                        @forelse($holds as $hold)
                            <tr>
                                <td>{{ $rowApplicantLabel($hold) }}</td>
                                <td><span class="badge text-bg-{{ $hold->status === 'held' ? 'success' : 'secondary' }}">{{ str($hold->status)->headline() }}</span></td>
                                <td>{{ $hold->expires_at }}</td>
                                <td>
                                    @if($hold->status === 'held')
                                        <form method="POST" action="{{ route('admission.seat-control.release', $hold->id) }}" onsubmit="return confirm('Release this held seat and check waitlist promotion?')">
                                            @csrf
                                            <input type="hidden" name="reason" value="Manual release from seat control">
                                            <button class="btn btn-sm btn-outline-danger" @disabled(! $canManageSeatControl)>Release</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted text-center py-3">No active seat holds.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">Deferrals And Joining Kit</div>
                <div class="card-body py-2">
                    <form method="POST" action="{{ route('admission.deferrals.store') }}" class="row g-1" onsubmit="return confirm('Request deferral for this applicant?')">
                        @csrf
                        <div class="col-12">
                            <select name="applicant_id" class="form-select form-select-sm" aria-label="Deferral applicant" required>
                                <option value="">Select applicant</option>
                                @foreach($selectedApplicants as $applicant)
                                    <option value="{{ $applicant->id }}">{{ $candidateLabel($applicant) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <select name="to_batch_id" class="form-select form-select-sm" aria-label="Target batch">
                                @foreach($batches as $batch)
                                    <option value="{{ $batch->id }}">{{ $batch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12"><input name="reason" class="form-control form-control-sm" value="Applicant requested future batch" aria-label="Deferral reason"></div>
                        <div class="col-12"><button class="btn btn-sm btn-primary" @disabled(! $canManageSeatControl)>Request Deferral</button></div>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Joining kit tasks">
                        <thead><tr><th>Task</th><th>Status</th><th>Applicant</th></tr></thead>
                        <tbody>
                        @forelse($joiningTasks as $task)
                            <tr>
                                <td>{{ $task->title }}</td>
                                <td><span class="badge text-bg-secondary">{{ str($task->status)->headline() }}</span></td>
                                <td>{{ $rowApplicantLabel($task) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted text-center py-3">No joining-kit tasks prepared yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white fw-bold">Deferral Requests</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0" aria-label="Deferral requests">
                <thead><tr><th>Applicant</th><th>Target Batch</th><th>Status</th><th>Reason</th><th>Approval</th></tr></thead>
                <tbody>
                @forelse($deferrals as $deferral)
                    <tr>
                        <td>{{ $rowApplicantLabel($deferral) }}</td>
                        <td>{{ $deferral->target_batch_name ?: 'Target batch pending' }}</td>
                        <td><span class="badge text-bg-secondary">{{ str($deferral->status)->headline() }}</span></td>
                        <td>{{ \Illuminate\Support\Str::limit($deferral->reason, 70) }}</td>
                        <td>
                            @if($deferral->status !== 'approved')
                                <form method="POST" action="{{ route('admission.deferrals.approve', $deferral->id) }}" class="d-flex gap-1" onsubmit="return confirm('Approve this deferral and update the applicant batch?')">
                                    @csrf
                                    <input name="carry_forward_notes" class="form-control form-control-sm" value="Approved from seat control review" aria-label="Carry forward notes">
                                    <button class="btn btn-sm btn-outline-success" @disabled(! $canManageSeatControl)>Approve</button>
                                </form>
                            @else
                                {{ $deferral->carry_forward_notes ?: '-' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted text-center py-3">No deferral requests.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
