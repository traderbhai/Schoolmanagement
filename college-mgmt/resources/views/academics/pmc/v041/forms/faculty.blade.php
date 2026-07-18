<form method="POST" action="{{ route('academics.pmc.section-faculty-allocation.assign') }}" class="card shadow-sm">@csrf
    <div class="card-header py-2 fw-semibold">Assign Faculty To Group</div>
    <div class="card-body vstack gap-2">
        <select aria-label="Course Group" class="form-select form-select-sm" name="course_group_id" required><option value="">Select section/group</option>@foreach($selectorOptions['courseGroups'] ?? [] as $group)<option value="{{ $group->id }}">{{ $group->name }} - {{ $group->subject?->code ?: $group->subject?->name }}</option>@endforeach</select>
        <select aria-label="Teacher" class="form-select form-select-sm" name="teacher_id" required><option value="">Select faculty</option>@foreach($selectorOptions['teachers'] ?? [] as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->user?->name ?? $teacher->employee_id ?? 'Unassigned faculty' }}{{ $teacher->employee_id ? ' - ' . $teacher->employee_id : '' }}</option>@endforeach</select>
        <select aria-label="Assignment Role" class="form-select form-select-sm" name="assignment_role" required>
            @foreach(['primary','co_faculty','lab_faculty','tutorial_faculty','backup','area_chair_recommended'] as $role)<option value="{{ $role }}">{{ str($role)->headline() }}</option>@endforeach
        </select>
        <input aria-label="Weekly hours" class="form-control form-control-sm" name="weekly_hours" placeholder="Weekly hours" value="3">
        <textarea aria-label="Reason / notes" class="form-control form-control-sm" name="notes" placeholder="Reason / notes"></textarea>
        <button class="btn btn-sm btn-primary">Assign Faculty</button>
    </div>
</form>
