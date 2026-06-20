@extends('layouts.admin')

@section('title', 'Assessment Scheduling')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">Assessment Scheduling</h3>
            <div class="text-muted small">Slots, evaluator readiness, room conflicts, GD groups, submissions, check-in, and reschedule control.</div>
            @unless($canManageAssessmentScheduling)
                <div class="small text-warning">Read-only view for your Admission scope. Scheduling, assignment, check-in, and evaluator changes require Admission leadership approval.</div>
            @endunless
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('admission.assessment-control-room.index') }}">Control Room</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admission.v039.exports','assessment-scheduling') }}">Export</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">Create Slot</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admission.assessment-slots.store') }}" class="row g-2" onsubmit="return confirm('Create this assessment slot?')">
                        @csrf
                        <div class="col-12">
                            <label class="form-label small">Panel</label>
                            <select name="panel_id" class="form-select form-select-sm">
                                @foreach($panels as $panel)<option value="{{ $panel->id }}">{{ $panel->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Resource / Room</label>
                            <select name="resource_id" class="form-select form-select-sm">
                                <option value="">No resource</option>
                                @foreach($resources as $resource)<option value="{{ $resource->id }}">{{ $resource->name }} - cap {{ $resource->capacity }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-6"><label class="form-label small">Code</label><input name="slot_code" class="form-control form-control-sm" value="PGDM-{{ now()->format('Hi') }}"></div>
                        <div class="col-6"><label class="form-label small">Capacity</label><input name="capacity" type="number" value="6" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label small">Start</label><input name="starts_at" type="datetime-local" class="form-control form-control-sm" value="{{ now()->addDay()->format('Y-m-d\\TH:i') }}"></div>
                        <div class="col-6"><label class="form-label small">End</label><input name="ends_at" type="datetime-local" class="form-control form-control-sm" value="{{ now()->addDay()->addHour()->format('Y-m-d\\TH:i') }}"></div>
                        <div class="col-12"><button class="btn btn-sm btn-primary" @disabled(! $canManageAssessmentScheduling)>Create Slot</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">Slots</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Assessment slots">
                        <thead><tr><th>Code</th><th>Starts</th><th>Capacity</th><th>Status</th><th>Assign</th><th>Bulk Assign</th></tr></thead>
                        <tbody>
                        @foreach($slots as $slot)
                            <tr>
                                <td>{{ $slot->slot_code }}</td>
                                <td>{{ $slot->starts_at }}</td>
                                <td>{{ $slot->capacity }}</td>
                                <td>{{ $slot->status }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admission.assessment-slots.assign') }}" class="d-flex gap-1" onsubmit="return confirm('Assign this applicant to the selected assessment slot?')">
                                        @csrf
                                        <input type="hidden" name="slot_id" value="{{ $slot->id }}">
                                        <select name="applicant_id" class="form-select form-select-sm" aria-label="Applicant">
                                            @foreach($applicants as $applicant)<option value="{{ $applicant->id }}">{{ $applicant->application_number }} - {{ $applicant->user?->name }}</option>@endforeach
                                        </select>
                                        <button class="btn btn-sm btn-outline-primary" @disabled(! $canManageAssessmentScheduling)>Assign</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admission.assessment-slots.bulk-assign') }}" class="d-flex gap-1" onsubmit="return confirm('Bulk assign selected applicants to this assessment slot?')">
                                        @csrf
                                        <input type="hidden" name="slot_id" value="{{ $slot->id }}">
                                        <select name="applicant_ids[]" multiple class="form-select form-select-sm" aria-label="Bulk applicants">
                                            @foreach($applicants->take(12) as $applicant)<option value="{{ $applicant->id }}">{{ $applicant->application_number }}</option>@endforeach
                                        </select>
                                        <button class="btn btn-sm btn-outline-secondary" @disabled(! $canManageAssessmentScheduling)>Bulk</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white">{{ $slots->links() }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">Evaluator Invitations</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Evaluator invitations">
                        <thead><tr><th>Panel</th><th>User</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                        @foreach($invitations as $invite)
                            <tr>
                                <td>{{ $panelNames[$invite->panel_id] ?? 'Panel pending' }}</td>
                                <td>{{ $userNames[$invite->user_id] ?? 'Evaluator pending' }}</td>
                                <td>{{ $invite->status }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admission.assessment-evaluator-invitations.respond') }}" class="d-inline" onsubmit="return confirm('Mark this evaluator invitation as accepted?')">
                                        @csrf
                                        <input type="hidden" name="invitation_id" value="{{ $invite->id }}">
                                        <input type="hidden" name="status" value="accepted">
                                        <button class="btn btn-sm btn-outline-success" @disabled(! $canManageAssessmentScheduling)>Accept</button>
                                    </form>
                                    <form method="POST" action="{{ route('admission.assessment-evaluator-invitations.replace') }}" class="d-flex gap-1 mt-1" onsubmit="return confirm('Invite the selected replacement evaluator?')">
                                        @csrf
                                        <input type="hidden" name="invitation_id" value="{{ $invite->id }}">
                                        <select name="replacement_user_id" class="form-select form-select-sm" aria-label="Replacement evaluator">
                                            @foreach($evaluators as $evaluator)<option value="{{ $evaluator->id }}">{{ $evaluator->name }}</option>@endforeach
                                        </select>
                                        <button class="btn btn-sm btn-outline-warning" @disabled(! $canManageAssessmentScheduling)>Replace</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">GD Builder</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admission.gd-groups.build') }}" class="row g-2" onsubmit="return confirm('Build GD groups for this panel?')">
                        @csrf
                        <div class="col-8"><select name="panel_id" class="form-select form-select-sm">@foreach($panels as $panel)<option value="{{ $panel->id }}">{{ $panel->name }}</option>@endforeach</select></div>
                        <div class="col-4"><input name="capacity" type="number" class="form-control form-control-sm" value="6"></div>
                        <div class="col-12"><button class="btn btn-sm btn-primary" @disabled(! $canManageAssessmentScheduling)>Build Groups</button></div>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="GD groups"><tbody>@foreach($gdGroups as $group)<tr><td>Group {{ $group->group_number }}</td><td>{{ $group->topic }}</td><td>{{ $group->status }}</td></tr>@endforeach</tbody></table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">Submissions</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admission.assessment-submissions.store') }}" class="row g-2" onsubmit="return confirm('Update this assessment submission status?')">
                        @csrf
                        <div class="col-12"><select name="applicant_id" class="form-select form-select-sm" aria-label="Applicant for submission">@foreach($applicants as $applicant)<option value="{{ $applicant->id }}">{{ $applicant->application_number }} - {{ $applicant->user?->name }}</option>@endforeach</select></div>
                        <div class="col-6"><select name="submission_type" class="form-select form-select-sm"><option>case_analysis</option><option>wat</option><option>presentation</option></select></div>
                        <div class="col-6"><select name="status" class="form-select form-select-sm"><option>received</option><option>late</option><option>missing</option></select></div>
                        <div class="col-12"><input name="artifact_url" class="form-control form-control-sm" placeholder="Submission link placeholder"></div>
                        <div class="col-12"><button class="btn btn-sm btn-primary" @disabled(! $canManageAssessmentScheduling)>Mark Submission</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white fw-bold">Candidate Check-In Desk</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0" aria-label="Assessment check-in desk">
                <thead><tr><th>Assignment</th><th>Applicant</th><th>Status</th><th>Change Status</th></tr></thead>
                <tbody>
                @foreach($assignments as $assignment)
                    <tr>
                        <td>{{ $assignment->slot_id ? 'Slot '.$assignment->slot_id : 'Assessment slot' }}</td>
                        <td>{{ $applicantNames[$assignment->applicant_id] ?? 'Applicant pending' }}</td>
                        <td>{{ $assignment->status }}</td>
                        <td>
                            <form method="POST" action="{{ route('admission.assessment-slots.check-in') }}" class="d-flex gap-1" onsubmit="return confirm('Update this candidate assessment lifecycle status?')">
                                @csrf
                                <input type="hidden" name="assignment_id" value="{{ $assignment->id }}">
                                <select name="status" class="form-select form-select-sm" aria-label="Lifecycle status">
                                    @foreach(['invited','confirmed','checked_in','waiting','in_progress','completed','no_show','rescheduled','cancelled'] as $state)
                                        <option value="{{ $state }}">{{ str_replace('_',' ',$state) }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-outline-primary" @disabled(! $canManageAssessmentScheduling)>Update</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white fw-bold">Resource Conflicts</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0" aria-label="Assessment resource conflicts">
                <thead><tr><th>Resource</th><th>Starts</th><th>Ends</th><th>Status</th></tr></thead>
                <tbody>@forelse($conflicts as $conflict)<tr><td>{{ $resourceNames[$conflict->resource_id] ?? 'Resource pending' }}</td><td>{{ $conflict->starts_at }}</td><td>{{ $conflict->ends_at }}</td><td>Conflict</td></tr>@empty<tr><td colspan="4" class="text-muted text-center">No resource conflicts.</td></tr>@endforelse</tbody>
            </table>
        </div>
    </div>
</div>
@endsection
