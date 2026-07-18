@extends('layouts.admin')

@section('title', 'Assessment Scheduling')

@section('content')
<div class="container-fluid py-3">
    <x-ui.page-header
        title="Assessment Scheduling"
        subtitle="Create slots, assign candidates, confirm evaluators/resources, build groups, track submissions, and run check-in."
        action-label="Control Room"
        :action-route="route('admission.assessment-control-room.index')"
        action-icon="bi-display"
    />

    <div class="alert alert-primary border-0 shadow-sm d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 py-3 mb-3">
        <div class="d-flex gap-3">
            <div class="ui-kpi-tile-icon bg-white text-primary"><i class="bi bi-calendar2-check"></i></div>
            <div>
                <div class="fw-bold">Scheduling workflow</div>
                <div class="small">1. Create slots/resources &nbsp; 2. Assign candidates &nbsp; 3. Confirm evaluators &nbsp; 4. Build GD/submission queues &nbsp; 5. Run check-in and resolve conflicts.</div>
                @unless($canManageAssessmentScheduling)
                    <div class="small text-warning mt-1">Read-only view for your Admission scope. Scheduling, assignment, check-in, and evaluator changes require Admission leadership approval.</div>
                @endunless
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary btn-sm" href="{{ route('admission.v039.exports','assessment-scheduling') }}">Export</a>
            <a class="btn btn-outline-primary btn-sm" href="{{ route('admission.assessment-schedule-conflicts.index') }}">Conflict Queue</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-bold">Create Slot</span><span class="small text-muted">Start assessment logistics here</span></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admission.assessment-slots.store') }}" class="row g-2" onsubmit="return confirm('Create this assessment slot? Confirm panel, resource, capacity, date/time, and evaluator readiness before candidates can be assigned or notified.')">
                        @csrf
                        <div class="col-12">
                            <label class="form-label small">Panel</label>
                            <select aria-label="Panel" name="panel_id" class="form-select form-select-sm">
                                @foreach($panels as $panel)<option value="{{ $panel->id }}">{{ $panel->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Resource / Room</label>
                            <select aria-label="Resource" name="resource_id" class="form-select form-select-sm">
                                <option value="">No resource</option>
                                @foreach($resources as $resource)<option value="{{ $resource->id }}">{{ $resource->name }} - cap {{ $resource->capacity }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-6"><label class="form-label small">Code</label><input aria-label="Slot Code" name="slot_code" class="form-control form-control-sm" value="PGDM-{{ now()->format('Hi') }}"></div>
                        <div class="col-6"><label class="form-label small">Capacity</label><input aria-label="Capacity" name="capacity" type="number" value="6" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label small">Start</label><input aria-label="Starts At" name="starts_at" type="datetime-local" class="form-control form-control-sm" value="{{ now()->addDay()->format('Y-m-d\\TH:i') }}"></div>
                        <div class="col-6"><label class="form-label small">End</label><input aria-label="Ends At" name="ends_at" type="datetime-local" class="form-control form-control-sm" value="{{ now()->addDay()->addHour()->format('Y-m-d\\TH:i') }}"></div>
                        <div class="col-12"><button type="submit" class="btn btn-sm btn-primary" @disabled(! $canManageAssessmentScheduling)>Create assessment slot</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-bold">Slots</span><span class="small text-muted">Assign candidates or bulk-fill capacity</span></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Assessment slots">
                        <thead><tr><th scope="col">Code</th><th scope="col">Starts</th><th scope="col">Capacity</th><th scope="col">Status</th><th scope="col">Assign</th><th scope="col">Bulk Assign</th></tr></thead>
                        <tbody>
                        @foreach($slots as $slot)
                            <tr>
                                <td>{{ $slot->slot_code }}</td>
                                <td>{{ $slot->starts_at }}</td>
                                <td>{{ $slot->capacity }}</td>
                                <td>{{ $slot->status }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admission.assessment-slots.assign') }}" class="d-flex gap-1" onsubmit="return confirm('Assign this applicant to the selected assessment slot? Confirm the candidate status, program scope, capacity, and schedule conflict queue before updating assessment logistics.')">
                                        @csrf
                                        <input type="hidden" name="slot_id" value="{{ $slot->id }}">
                                        <select name="applicant_id" class="form-select form-select-sm" aria-label="Applicant">
                                            @foreach($applicants as $applicant)<option value="{{ $applicant->id }}">{{ $applicant->application_number }} - {{ $applicant->user?->name }}</option>@endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-primary" @disabled(! $canManageAssessmentScheduling)>Assign candidate</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admission.assessment-slots.bulk-assign') }}" class="d-flex gap-1" onsubmit="return confirm('Bulk assign selected applicants to this assessment slot? Confirm all selected candidates match the slot scope and capacity before changing their assessment schedule.')">
                                        @csrf
                                        <input type="hidden" name="slot_id" value="{{ $slot->id }}">
                                        <select name="applicant_ids[]" multiple class="form-select form-select-sm" aria-label="Bulk applicants">
                                            @foreach($applicants->take(12) as $applicant)<option value="{{ $applicant->id }}">{{ $applicant->application_number }}</option>@endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled(! $canManageAssessmentScheduling)>Bulk assign candidates</button>
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
                <div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-bold">Evaluator Invitations</span><span class="small text-muted">Accept, replace, and clear readiness warnings</span></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Evaluator invitations">
                        <thead><tr><th scope="col">Panel</th><th scope="col">User</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                        <tbody>
                        @foreach($invitations as $invite)
                            <tr>
                                <td>{{ $panelNames[$invite->panel_id] ?? 'Panel pending' }}</td>
                                <td>{{ $userNames[$invite->user_id] ?? 'Evaluator pending' }}</td>
                                <td>{{ $invite->status }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admission.assessment-evaluator-invitations.respond') }}" class="d-inline" onsubmit="return confirm('Mark this evaluator invitation as accepted? Confirm the evaluator is available for the panel and understands the assessment responsibilities.')">
                                        @csrf
                                        <input type="hidden" name="invitation_id" value="{{ $invite->id }}">
                                        <input type="hidden" name="status" value="accepted">
                                        <button type="submit" class="btn btn-sm btn-outline-success" @disabled(! $canManageAssessmentScheduling)>Accept evaluator invite</button>
                                    </form>
                                    <form method="POST" action="{{ route('admission.assessment-evaluator-invitations.replace') }}" class="d-flex gap-1 mt-1" onsubmit="return confirm('Invite the selected replacement evaluator? Confirm panel fit, availability, conflict impact, and candidate communication before replacing the evaluator.')">
                                        @csrf
                                        <input type="hidden" name="invitation_id" value="{{ $invite->id }}">
                                        <select name="replacement_user_id" class="form-select form-select-sm" aria-label="Replacement evaluator">
                                            @foreach($evaluators as $evaluator)<option value="{{ $evaluator->id }}">{{ $evaluator->name }}</option>@endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-warning" @disabled(! $canManageAssessmentScheduling)>Replace evaluator</button>
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
                <div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-bold">GD Builder</span><span class="small text-muted">Build groups after slot assignment</span></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admission.gd-groups.build') }}" class="row g-2" onsubmit="return confirm('Build GD groups for this panel? Confirm assigned candidates, group capacity, evaluator availability, and conflict queue before creating groups.')">
                        @csrf
                        <div class="col-8"><select aria-label="Panel" name="panel_id" class="form-select form-select-sm">@foreach($panels as $panel)<option value="{{ $panel->id }}">{{ $panel->name }}</option>@endforeach</select></div>
                        <div class="col-4"><input aria-label="Capacity" name="capacity" type="number" class="form-control form-control-sm" value="6"></div>
                        <div class="col-12"><button type="submit" class="btn btn-sm btn-primary" @disabled(! $canManageAssessmentScheduling)>Build GD groups</button></div>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="GD groups"><tbody>@foreach($gdGroups as $group)<tr><td>Group {{ $group->group_number }}</td><td>{{ $group->topic }}</td><td>{{ $group->status }}</td></tr>@endforeach</tbody></table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-bold">Submissions</span><span class="small text-muted">Mark WAT/case/presentation evidence</span></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admission.assessment-submissions.store') }}" class="row g-2" onsubmit="return confirm('Update this assessment submission status? Confirm the applicant, submission evidence, late/missing state, and score-readiness impact before saving.')">
                        @csrf
                        <div class="col-12"><select name="applicant_id" class="form-select form-select-sm" aria-label="Applicant for submission">@foreach($applicants as $applicant)<option value="{{ $applicant->id }}">{{ $applicant->application_number }} - {{ $applicant->user?->name }}</option>@endforeach</select></div>
                        <div class="col-6"><select aria-label="Submission Type" name="submission_type" class="form-select form-select-sm"><option>case_analysis</option><option>wat</option><option>presentation</option></select></div>
                        <div class="col-6"><select aria-label="Status" name="status" class="form-select form-select-sm"><option>received</option><option>late</option><option>missing</option></select></div>
                        <div class="col-12"><input aria-label="Submission artifact URL" name="artifact_url" class="form-control form-control-sm" placeholder="Submission link placeholder"></div>
                        <div class="col-12"><button type="submit" class="btn btn-sm btn-primary" @disabled(! $canManageAssessmentScheduling)>Mark assessment submission</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-bold">Candidate Check-In Desk</span><span class="small text-muted">Move candidates through invited, confirmed, checked-in, waiting, in-progress, completed, no-show, rescheduled</span></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0" aria-label="Assessment check-in desk">
                <thead><tr><th scope="col">Assignment</th><th scope="col">Applicant</th><th scope="col">Status</th><th scope="col">Change Status</th></tr></thead>
                <tbody>
                @foreach($assignments as $assignment)
                    <tr>
                        <td>{{ $assignment->slot_id ? 'Slot '.$assignment->slot_id : 'Assessment slot' }}</td>
                        <td>{{ $applicantNames[$assignment->applicant_id] ?? 'Applicant pending' }}</td>
                        <td>{{ $assignment->status }}</td>
                        <td>
                            <form method="POST" action="{{ route('admission.assessment-slots.check-in') }}" class="d-flex gap-1" onsubmit="return confirm('Update this candidate assessment lifecycle status? Confirm attendance/check-in evidence, panel readiness, and downstream scoring impact before saving.')">
                                @csrf
                                <input type="hidden" name="assignment_id" value="{{ $assignment->id }}">
                                <select name="status" class="form-select form-select-sm" aria-label="Lifecycle status">
                                    @foreach(['invited','confirmed','checked_in','waiting','in_progress','completed','no_show','rescheduled','cancelled'] as $state)
                                        <option value="{{ $state }}">{{ str_replace('_',' ',$state) }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary" @disabled(! $canManageAssessmentScheduling)>Update candidate status</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-bold">Resource Conflicts</span><span class="small text-muted">Resolve room/evaluator overlaps before assessment day</span></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0" aria-label="Assessment resource conflicts">
                <thead><tr><th scope="col">Resource</th><th scope="col">Starts</th><th scope="col">Ends</th><th scope="col">Status</th></tr></thead>
                <tbody>@forelse($conflicts as $conflict)<tr><td>{{ $resourceNames[$conflict->resource_id] ?? 'Resource pending' }}</td><td>{{ $conflict->starts_at }}</td><td>{{ $conflict->ends_at }}</td><td>Conflict</td></tr>@empty<tr><td colspan="4" class="text-muted text-center">No resource conflicts.</td></tr>@endforelse</tbody>
            </table>
        </div>
    </div>
</div>
@endsection
