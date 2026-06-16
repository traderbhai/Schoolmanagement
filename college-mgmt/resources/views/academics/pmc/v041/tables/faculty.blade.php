<div class="card shadow-sm mb-3">
    <div class="card-header py-2 fw-semibold">Faculty Assigned To Exact Groups</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Group</th><th>Faculty</th><th>Role</th><th>Hours</th><th>Status</th><th>Ack</th></tr></thead>
        <tbody>@forelse($assignments as $assignment)<tr><td>{{ $assignment->courseGroup?->name }}</td><td>{{ $assignment->teacher?->user?->name ?? $assignment->teacher?->employee_id ?? 'Unassigned faculty' }}</td><td>{{ $assignment->assignment_role }}</td><td>{{ $assignment->weekly_hours }}</td><td>{{ $assignment->approval_status }}</td><td><form method="POST" action="{{ route('academics.pmc.faculty-assignment-acknowledgements.request', $assignment) }}">@csrf<button class="btn btn-sm btn-outline-primary">Request</button></form></td></tr>@empty<tr><td colspan="6" class="text-muted">No group faculty assignments.</td></tr>@endforelse</tbody>
    </table></div><div class="card-footer py-2">{{ $assignments->links() }}</div>
</div>
@isset($acknowledgements)
<div class="card shadow-sm mb-3">
    <div class="card-header py-2 fw-semibold">Faculty Assignment Acknowledgements</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Faculty</th><th>Assignment</th><th>Status</th><th>Faculty Response</th><th>PMC Review</th></tr></thead>
        <tbody>@forelse($acknowledgements as $ack)<tr>
            <td>{{ $ack->teacher?->user?->name ?? $ack->teacher?->employee_id ?? 'Unassigned faculty' }}</td>
            <td><div class="fw-semibold">{{ $ack->assignment?->courseGroup?->name }}</div><div class="small text-muted">{{ $ack->assignment?->assignment_role }}</div></td>
            <td>
                {{ str_replace('_', ' ', $ack->status) }}
                <div class="small text-muted">{{ $ack->response_type ? str_replace('_', ' ', $ack->response_type) : 'awaiting response' }}</div>
                @if(!empty($ack->constraints_raised))
                    <div class="small text-warning">{{ collect($ack->constraints_raised)->join(', ') }}</div>
                @endif
            </td>
            <td>
                <form method="POST" action="{{ route('academics.pmc.faculty-assignment-acknowledgements.respond', $ack) }}" class="d-flex flex-column gap-1">
                    @csrf @method('PATCH')
                    <select name="response_type" class="form-select form-select-sm">
                        <option value="accept">accept</option>
                        <option value="accept_with_constraints">accept with constraints</option>
                        <option value="raise_concern">raise concern</option>
                        <option value="decline">decline</option>
                    </select>
                    <input name="constraints_raised" class="form-control form-control-sm" placeholder="constraints, comma-separated">
                    <input name="faculty_note" class="form-control form-control-sm" placeholder="Faculty note">
                    <button class="btn btn-sm btn-outline-primary">Respond</button>
                </form>
            </td>
            <td>
                <form method="POST" action="{{ route('academics.pmc.faculty-assignment-acknowledgements.review', $ack) }}" class="d-flex flex-column gap-1">
                    @csrf @method('PATCH')
                    <select name="status" class="form-select form-select-sm">
                        <option value="accepted">accepted</option>
                        <option value="concern_accepted">concern accepted</option>
                        <option value="revision_required">revision required</option>
                        <option value="reassigned">reassigned</option>
                    </select>
                    <input name="review_note" class="form-control form-control-sm" placeholder="Review note">
                    <button class="btn btn-sm btn-outline-success">Review</button>
                </form>
            </td>
        </tr>@empty<tr><td colspan="5" class="text-muted">No faculty assignment acknowledgements.</td></tr>@endforelse</tbody>
    </table></div><div class="card-footer py-2">{{ $acknowledgements->links() }}</div>
</div>
@endisset
@isset($loadReviews)
<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Faculty Load Reviews</span>
        <form method="POST" action="{{ route('academics.pmc.faculty-load-reviews.refresh') }}" class="d-flex gap-1">@csrf
            <button class="btn btn-sm btn-outline-primary">Refresh Load Reviews</button>
        </form>
    </div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Faculty</th><th>Hours</th><th>Classes</th><th>Daily Max</th><th>Band</th><th>Status</th><th>Decision</th></tr></thead>
        <tbody>@forelse($loadReviews as $review)<tr>
            <td><div class="fw-semibold">{{ $review->teacher?->user?->name }}</div><div class="small text-muted">{{ collect($review->risk_reasons ?? [])->join(', ') ?: 'clear' }}</div></td>
            <td>{{ $review->assigned_weekly_hours }}/{{ $review->configured_weekly_limit }}</td>
            <td>{{ $review->scheduled_classes }}</td>
            <td>{{ $review->max_classes_in_day }}/{{ $review->configured_daily_limit }}</td>
            <td>{{ $review->load_band }}</td>
            <td>{{ $review->status }}</td>
            <td>@if(!in_array($review->status, ['approved','approved_overload','rejected'], true))<form method="POST" action="{{ route('academics.pmc.faculty-load-reviews.decide', $review) }}" class="d-flex gap-1">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ in_array($review->load_band, ['overload','critical'], true) ? 'approved_overload' : 'approved' }}"><input class="form-control form-control-sm" name="decision_note" placeholder="Decision note"><button class="btn btn-sm btn-outline-success">Approve</button></form>@else {{ $review->decision_note ?: '-' }} @endif</td>
        </tr>@empty<tr><td colspan="7" class="text-muted">No faculty load reviews yet.</td></tr>@endforelse</tbody>
    </table></div><div class="card-footer py-2">{{ $loadReviews->links() }}</div>
</div>
@endisset
<div class="row g-3">
    <div class="col-md-6"><div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Faculty Preferences</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Faculty</th><th>Type</th><th>Max/Day</th><th>Weekly</th></tr></thead><tbody>@foreach($preferences as $preference)<tr><td>{{ $preference->teacher?->user?->name }}</td><td>{{ $preference->faculty_type }}</td><td>{{ $preference->max_classes_per_day }}</td><td>{{ $preference->max_weekly_load }}</td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $preferences->links() }}</div></div></div>
    <div class="col-md-6"><div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Workload Rules</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Rule</th><th>Normal</th><th>Overload</th><th>Active</th></tr></thead><tbody>@foreach($rules as $rule)<tr><td>{{ $rule->name }}</td><td>{{ $rule->normal_weekly_hours }}</td><td>{{ $rule->overload_threshold }}</td><td>{{ $rule->is_active ? 'yes' : 'no' }}</td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $rules->links() }}</div></div></div>
</div>
