<form method="POST" action="{{ route('academics.pmc.section-faculty-allocation.assign') }}" class="card shadow-sm">@csrf
    <div class="card-header py-2 fw-semibold">Assign Faculty To Group</div>
    <div class="card-body vstack gap-2">
        <input class="form-control form-control-sm" name="course_group_id" placeholder="Course group ID" required>
        <input class="form-control form-control-sm" name="teacher_id" placeholder="Teacher ID" required>
        <select class="form-select form-select-sm" name="assignment_role" required>
            @foreach(['primary','co_faculty','lab_faculty','tutorial_faculty','backup','area_chair_recommended'] as $role)<option value="{{ $role }}">{{ str($role)->headline() }}</option>@endforeach
        </select>
        <input class="form-control form-control-sm" name="weekly_hours" placeholder="Weekly hours" value="3">
        <textarea class="form-control form-control-sm" name="notes" placeholder="Reason / notes"></textarea>
        <button class="btn btn-sm btn-primary">Assign Faculty</button>
    </div>
</form>
