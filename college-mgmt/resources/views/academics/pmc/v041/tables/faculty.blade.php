<div class="card shadow-sm mb-3">
    <div class="card-header py-2 fw-semibold">Faculty Assigned To Exact Groups</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Group</th><th>Faculty</th><th>Role</th><th>Hours</th><th>Status</th></tr></thead>
        <tbody>@forelse($assignments as $assignment)<tr><td>{{ $assignment->courseGroup?->name }}</td><td>{{ $assignment->teacher?->user?->name ?? 'Teacher #' . $assignment->teacher_id }}</td><td>{{ $assignment->assignment_role }}</td><td>{{ $assignment->weekly_hours }}</td><td>{{ $assignment->approval_status }}</td></tr>@empty<tr><td colspan="5" class="text-muted">No group faculty assignments.</td></tr>@endforelse</tbody>
    </table></div><div class="card-footer py-2">{{ $assignments->links() }}</div>
</div>
<div class="row g-3">
    <div class="col-md-6"><div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Faculty Preferences</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Faculty</th><th>Type</th><th>Max/Day</th><th>Weekly</th></tr></thead><tbody>@foreach($preferences as $preference)<tr><td>{{ $preference->teacher?->user?->name }}</td><td>{{ $preference->faculty_type }}</td><td>{{ $preference->max_classes_per_day }}</td><td>{{ $preference->max_weekly_load }}</td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $preferences->links() }}</div></div></div>
    <div class="col-md-6"><div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Workload Rules</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Rule</th><th>Normal</th><th>Overload</th><th>Active</th></tr></thead><tbody>@foreach($rules as $rule)<tr><td>{{ $rule->name }}</td><td>{{ $rule->normal_weekly_hours }}</td><td>{{ $rule->overload_threshold }}</td><td>{{ $rule->is_active ? 'yes' : 'no' }}</td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $rules->links() }}</div></div></div>
</div>
