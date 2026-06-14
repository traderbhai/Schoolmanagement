<form method="POST" action="{{ route('academics.pmc.course-groups.store') }}" class="card shadow-sm">@csrf
    <div class="card-header py-2 fw-semibold">Create Section / Group</div>
    <div class="card-body vstack gap-2">
        <input class="form-control form-control-sm" name="name" placeholder="Group name" required>
        <select class="form-select form-select-sm" name="group_type" required>
            @foreach(['core_section','elective_group','lab_group','tutorial_group','project_group','combined_section'] as $type)<option value="{{ $type }}">{{ str($type)->headline() }}</option>@endforeach
        </select>
        <input class="form-control form-control-sm" name="program_id" placeholder="Program ID">
        <input class="form-control form-control-sm" name="batch_id" placeholder="Batch ID">
        <input class="form-control form-control-sm" name="term_id" placeholder="Term ID">
        <input class="form-control form-control-sm" name="subject_id" placeholder="Subject ID">
        <div class="d-flex gap-2"><input class="form-control form-control-sm" name="min_capacity" value="1"><input class="form-control form-control-sm" name="max_capacity" value="60"></div>
        <input class="form-control form-control-sm" name="current_strength" value="0">
        <button class="btn btn-sm btn-primary">Create Group</button>
    </div>
</form>
