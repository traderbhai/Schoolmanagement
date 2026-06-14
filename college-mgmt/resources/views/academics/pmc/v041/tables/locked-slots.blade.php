<div class="card shadow-sm mb-3">
    <div class="card-header py-2 fw-semibold">Locked / Manual Slots</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Slot</th><th>Type</th><th>Day</th><th>Lock</th><th>Reason</th></tr></thead><tbody>
        @forelse($lockedSlots as $slot)<tr><td><div class="fw-semibold">{{ $slot->title }}</div><div class="small text-muted">{{ $slot->courseGroup?->name }}</div></td><td>{{ $slot->slot_type }}</td><td>{{ $slot->day_of_week }}</td><td>{{ $slot->is_hard_lock ? 'hard' : 'soft' }}</td><td>{{ $slot->reason }}</td></tr>@empty<tr><td colspan="5" class="text-muted">No locked slots.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer py-2">{{ $lockedSlots->links() }}</div>
</div>
<div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Timetable Input Readiness</div><div class="list-group list-group-flush">@foreach($readiness as $item)<div class="list-group-item py-2 d-flex justify-content-between"><span>{{ $item['label'] }}</span><span class="badge text-bg-{{ $item['ready'] ? 'success' : 'warning' }}">{{ $item['ready'] ? 'ready' : 'blocked' }}</span></div>@endforeach</div></div>
