<div class="card shadow-sm mb-3">
    <div class="card-header py-2 fw-semibold">Sections And Groups</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Group</th><th>Type</th><th>Subject</th><th>Strength</th><th>Status</th><th>Lock</th></tr></thead>
        <tbody>@forelse($groups as $group)<tr>
            <td><div class="fw-semibold">{{ $group->name }}</div><div class="small text-muted">{{ $group->program?->code ?? 'All programs' }}</div></td><td>{{ $group->group_type }}</td><td>{{ $group->subject?->name ?? '-' }}</td><td>{{ $group->current_strength }}/{{ $group->max_capacity }}</td><td>{{ $group->status }}</td><td>{{ $group->is_locked ? 'locked' : 'open' }}</td>
        </tr>@empty<tr><td colspan="6" class="text-muted">No sections or groups.</td></tr>@endforelse</tbody>
    </table></div><div class="card-footer py-2">{{ $groups->links() }}</div>
</div>
<div class="card shadow-sm">
    <div class="card-header py-2 fw-semibold">Group Memberships</div>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Group</th><th>Student</th><th>Status</th></tr></thead><tbody>
        @forelse($memberships as $member)<tr><td>{{ $member->courseGroup?->name }}</td><td>{{ $member->student?->user?->name ?? 'Student #' . $member->student_id }}</td><td>{{ $member->status }}</td></tr>@empty<tr><td colspan="3" class="text-muted">No group memberships.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer py-2">{{ $memberships->links() }}</div>
</div>
