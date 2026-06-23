<div class="card shadow-sm mb-3">
    <div class="card-header py-2 fw-semibold">Planning Board Grid</div>
    @php($plannerCells = collect($items->items())->groupBy(fn($item) => ($item->day_of_week ?: 0) . ':' . ($item->timetable_slot_id ?: 0)))
    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Day</th><th>Slot</th><th>Parallel Canonical Sessions</th><th>Utilization</th></tr></thead><tbody>
        @forelse($plannerCells as $cellKey => $cellItems)
            @php($first = $cellItems->first())
            <tr>
                <td>{{ $first->day_of_week }}</td>
                <td>{{ $first->slot?->name ?? $first->timetable_slot_id }}</td>
                <td>
                    <div class="d-flex flex-column gap-2">
                        @foreach($cellItems as $item)
                            <div class="border rounded p-2">
                                <a class="fw-semibold" href="{{ route('academics.pmc.canonical-sessions.show', $item) }}">{{ $item->courseGroup?->name ?? 'Session #' . $item->id }}</a>
                                <div class="small text-muted">{{ $item->courseGroup?->subject?->name }} | {{ $item->teacher?->user?->name ?? 'Faculty pending' }} | {{ $item->classroom?->name ?? 'Room pending' }}</div>
                                <div class="small">{{ $item->session_type }} | {{ $item->is_locked ? 'locked' : 'movable' }} | {{ $item->confidence }}%</div>
                            </div>
                        @endforeach
                    </div>
                </td>
                <td><span class="badge text-bg-{{ $cellItems->count() > 1 ? 'primary' : 'light' }}">{{ $cellItems->count() }} session(s)</span></td>
            </tr>
        @empty<tr><td colspan="4" class="text-muted">No scheduled timetable items.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer py-2">{{ $items->links() }}</div>
</div>
<div class="card shadow-sm"><div class="card-header py-2 d-flex justify-content-between align-items-center"><span class="fw-semibold">Conflict Panel</span><span class="small text-muted">Filtered Source List ({{ $constraints->total() }})</span></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Type</th><th>Severity</th><th>Issue</th><th>Fix</th><th>Action</th></tr></thead><tbody>@foreach($constraints as $constraint)<tr><td>{{ $constraint->constraint_type }}</td><td>{{ $constraint->severity }}</td><td>{{ $constraint->title }}</td><td>{{ $constraint->recommended_fix }}</td><td><form method="POST" action="{{ route('academics.pmc.timetable-constraints.resolution-actions.store', $constraint) }}" class="d-flex gap-1">@csrf<input type="hidden" name="title" value="Resolve {{ $constraint->title }}"><input type="hidden" name="description" value="{{ $constraint->recommended_fix }}"><button class="btn btn-xs btn-outline-primary py-0 px-1">Create Action</button></form></td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $constraints->links() }}</div></div>
@isset($resolutionActions)
<div class="card shadow-sm mt-3"><div class="card-header py-2 fw-semibold">Resolution Actions</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Action</th><th>Owner</th><th>Priority</th><th>Status</th><th>Due</th><th>Close</th></tr></thead><tbody>@forelse($resolutionActions as $action)<tr><td><div class="fw-semibold">{{ $action->title }}</div><div class="small text-muted">{{ $action->constraint?->title }}</div></td><td>{{ $action->owner?->name ?? 'PMC' }}</td><td>{{ $action->priority }}</td><td>{{ $action->status }}</td><td>{{ optional($action->due_at)->format('d M Y') }}</td><td>@if(!in_array($action->status, ['resolved','closed','verified','cancelled']))<form method="POST" action="{{ route('academics.pmc.timetable-resolution-actions.close', $action) }}" class="d-flex gap-1">@csrf @method('PATCH')<input type="hidden" name="status" value="resolved"><input class="form-control form-control-sm" name="resolution_note" placeholder="Resolution note" required><button class="btn btn-sm btn-outline-success">Close</button></form>@else {{ $action->resolution_note }} @endif</td></tr>@empty<tr><td colspan="6" class="text-muted">No resolution actions yet.</td></tr>@endforelse</tbody></table></div><div class="card-footer py-2">{{ $resolutionActions->links() }}</div></div>
@endisset
