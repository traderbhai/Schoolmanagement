<div class="card shadow-sm mb-3">
    <div class="card-header py-2 fw-semibold">Sections And Groups</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th scope="col">Group</th><th scope="col">Type</th><th scope="col">Subject</th><th scope="col">Strength</th><th scope="col">Status</th><th scope="col">Lock</th></tr></thead>
        <tbody>@forelse($groups as $group)<tr>
            <td><div class="fw-semibold">{{ $group->name }}</div><div class="small text-muted">{{ $group->program?->code ?? 'All programs' }}</div></td><td>{{ $group->group_type }}</td><td>{{ $group->subject?->name ?? '-' }}</td><td>{{ $group->current_strength }}/{{ $group->max_capacity }}</td><td>{{ $group->status }}</td><td>{{ $group->is_locked ? 'locked' : 'open' }}</td>
        </tr>@empty<tr><td colspan="6" class="text-muted">No sections or groups.</td></tr>@endforelse</tbody>
    </table></div><div class="card-footer py-2">{{ $groups->links() }}</div>
</div>
@isset($buildRuns)
<div class="card shadow-sm mt-3">
    <div class="card-header py-2 fw-semibold">Auto Group Build Runs</div>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col">Run</th><th scope="col">Type</th><th scope="col">Students</th><th scope="col">Groups</th><th scope="col">Warnings</th></tr></thead><tbody>
        @forelse($buildRuns as $run)<tr><td>{{ $run->title }}</td><td>{{ $run->group_type }}</td><td>{{ $run->students_considered }}</td><td>{{ $run->groups_created }}</td><td>{{ $run->warnings_count }}</td></tr>@empty<tr><td colspan="5" class="text-muted">No auto-build runs.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer py-2">{{ $buildRuns->links() }}</div>
</div>
@endisset
<div class="card shadow-sm">
    <div class="card-header py-2 fw-semibold">Group Memberships</div>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col">Group</th><th scope="col">Student</th><th scope="col">Status</th></tr></thead><tbody>
        @forelse($memberships as $member)<tr><td>{{ $member->courseGroup?->name }}</td><td>{{ $member->student?->user?->name ?? $member->student?->enrollment_number ?? $member->student?->roll_number ?? $member->student?->student_id ?? 'Unassigned student' }}</td><td>{{ $member->status }}</td></tr>@empty<tr><td colspan="3" class="text-muted">No group memberships.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer py-2">{{ $memberships->links() }}</div>
</div>
