@extends('layouts.admin')
@section('title', 'Dean Reviews And Actions')

@section('content')
@php
    $filters = $filters ?? [];
    $actionTotal = method_exists($actions, 'total') ? $actions->total() : $actions->count();
    $filterSummary = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->map(fn ($value, $key) => str($key)->headline().': '.$value)->join(' | ');
@endphp
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">Dean Reviews And Actions</h1><div class="small text-muted">Structured review meetings, action ownership, due dates, closure, and escalation tracking.</div></div>@include('academics.dean-os.partials.nav')</div>
    <div class="alert alert-info border-0 shadow-sm small mb-3">
        <div class="fw-semibold mb-1">Review-to-action sequence</div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-light border">1. Create review meeting</span>
            <span class="badge text-bg-light border">2. Record agenda or summary</span>
            <span class="badge text-bg-light border">3. Assign action owner</span>
            <span class="badge text-bg-light border">4. Track due date and priority</span>
            <span class="badge text-bg-light border">5. Close with note/evidence</span>
        </div>
        <div class="text-muted mt-2">Use this page when a risk, blocker, or approval needs a formal Dean review and accountable follow-up.</div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-xl-5">
            <form method="POST" action="{{ route('academics.dean-os.reviews.store') }}" class="card shadow-sm">@csrf
                <div class="card-header py-2"><div class="fw-semibold">Create Review Meeting</div><div class="small text-muted">Start here for weekly academic, exam, IQAC, handoff, or emergency reviews.</div></div>
                <div class="card-body row g-2">
                    <div class="col-12"><input aria-label="Review title" name="title" class="form-control form-control-sm" placeholder="Review title" required></div>
                    <div class="col-md-6"><select aria-label="Review Type" name="review_type" class="form-select form-select-sm" required>@foreach(['weekly_academic','program_review','attendance_review','exam_review','iqac_review','handoff_review','emergency_review'] as $type)<option value="{{ $type }}">{{ str_replace('_',' ', $type) }}</option>@endforeach</select></div>
                    <div class="col-md-6"><input aria-label="Scheduled For" type="datetime-local" name="scheduled_for" class="form-control form-control-sm"></div>
                    <div class="col-md-6"><select aria-label="Scope Type" name="scope_type" class="form-select form-select-sm"><option value="department">Department</option><option value="branch">Branch</option><option value="program">Program</option><option value="batch">Batch</option><option value="term">Term</option></select></div>
                    <div class="col-md-6"><input aria-label="Scope ID" name="scope_id" type="number" class="form-control form-control-sm" placeholder="Scope ID"></div>
                    <div class="col-12"><textarea aria-label="Summary / agenda" name="summary" class="form-control form-control-sm" rows="2" placeholder="Summary / agenda"></textarea></div>
                    <div class="col-12 text-end"><button class="btn btn-sm btn-primary" onclick="return confirm('Create this Dean review meeting? Confirm scope, agenda, owner expectations, and whether this needs formal follow-up before recording it.')">Create Meeting</button></div>
                </div>
            </form>
        </div>
        <div class="col-xl-7">
            <form method="POST" action="{{ route('academics.dean-os.actions.store') }}" class="card shadow-sm">@csrf
                <div class="card-header py-2"><div class="fw-semibold">Create Action Item</div><div class="small text-muted">Action items should have a clear source, owner, priority, and due date.</div></div>
                <div class="card-body row g-2">
                    <div class="col-md-6"><input aria-label="Action title" name="title" class="form-control form-control-sm" placeholder="Action title" required></div>
                    <div class="col-md-3"><select aria-label="Priority" name="priority" class="form-select form-select-sm">@foreach(['normal','low','high','critical'] as $p)<option value="{{ $p }}">{{ ucfirst($p) }}</option>@endforeach</select></div>
                    <div class="col-md-3"><input aria-label="Due At" type="datetime-local" name="due_at" class="form-control form-control-sm"></div>
                    <div class="col-md-4"><select aria-label="Meeting" name="meeting_id" class="form-select form-select-sm"><option value="">No meeting</option>@foreach($meetings as $meeting)<option value="{{ $meeting->id }}">{{ $meeting->title }}</option>@endforeach</select></div>
                    <div class="col-md-4"><select aria-label="Owner User" name="owner_user_id" class="form-select form-select-sm"><option value="">Unassigned</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select></div>
                    <div class="col-md-4"><select aria-label="Source Type" name="source_type" class="form-select form-select-sm">@foreach(['manual','pmc','coe','iqac','program','course_delivery','attendance','approval','handoff','grievance'] as $s)<option value="{{ $s }}">{{ str_replace('_',' ', $s) }}</option>@endforeach</select></div>
                    <div class="col-12"><textarea aria-label="Description" name="description" class="form-control form-control-sm" rows="2" placeholder="Description"></textarea></div>
                    <div class="col-12 text-end"><button class="btn btn-sm btn-primary" onclick="return confirm('Create this Dean action item? Confirm owner, priority, due date, source workflow, and escalation expectation before adding it to the tracker.')">Create Action</button></div>
                </div>
            </form>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header py-2">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <div class="fw-semibold">Action Tracker ({{ $actionTotal }})</div>
                    <div class="small text-muted">Visible filter summary: {{ $filterSummary ?: 'Showing all Dean action records.' }}</div>
                </div>
                <a href="{{ route('academics.dean-os.export', 'academic_actions') }}?{{ http_build_query($filters) }}" class="btn btn-sm btn-outline-secondary">Export current view</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th scope="col">Action</th><th scope="col">Owner</th><th scope="col">Priority</th><th scope="col">Due</th><th scope="col">Status</th><th scope="col">Update</th></tr></thead>
                <tbody>
                @foreach($actions as $action)
                    <tr>
                        <td><div class="fw-semibold">{{ $action->title }}</div><div class="small text-muted">{{ $action->description }}</div></td>
                        <td>{{ $action->owner?->name ?? 'Unassigned' }}</td>
                        <td><span class="badge text-bg-light">{{ $action->priority }}</span></td>
                        <td class="small">{{ $action->due_at?->toDateString() ?? '-' }}</td>
                        <td>{{ $action->status }}</td>
                        <td>
                            <form method="POST" action="{{ route('academics.dean-os.actions.update', $action) }}" class="d-flex gap-1">@csrf @method('PATCH')
                                <input type="hidden" name="owner_user_id" value="{{ $action->owner_user_id }}">
                                <input type="hidden" name="priority" value="{{ $action->priority }}">
                                <input type="hidden" name="due_at" value="{{ $action->due_at?->format('Y-m-d H:i:s') }}">
                                <select aria-label="Status" name="status" class="form-select form-select-sm">@foreach(['open','in_progress','blocked','done','cancelled'] as $status)<option value="{{ $status }}" @selected($action->status===$status)>{{ $status }}</option>@endforeach</select>
                                <input aria-label="Closure note required when closing without evidence" name="closure_note" class="form-control form-control-sm" placeholder="Closure note required when closing without evidence">
                                <button class="btn btn-sm btn-outline-primary" onclick="return confirm('Update this Dean action item? Confirm status, owner, due date, and closure evidence before changing the accountability record.')">Save action status</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    {{ $actions->links() }}
</div>
@endsection
