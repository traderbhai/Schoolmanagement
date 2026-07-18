<form method="POST" action="{{ route('academics.pmc.course-groups.store') }}" class="card shadow-sm">@csrf
    <div class="card-header py-2 fw-semibold">Create Section / Group</div>
    <div class="card-body vstack gap-2">
        <input aria-label="Group name" class="form-control form-control-sm" name="name" placeholder="Group name" required>
        <select aria-label="Group Type" class="form-select form-select-sm" name="group_type" required>
            @foreach(['core_section','elective_group','lab_group','tutorial_group','project_group','combined_section'] as $type)<option value="{{ $type }}">{{ str($type)->headline() }}</option>@endforeach
        </select>
        <select aria-label="Program" class="form-select form-select-sm" name="program_id"><option value="">Select program</option>@foreach($selectorOptions['programs'] ?? [] as $program)<option value="{{ $program->id }}">{{ $program->code ?: $program->name }} - {{ $program->name }}</option>@endforeach</select>
        <select aria-label="Batch" class="form-select form-select-sm" name="batch_id"><option value="">Select batch</option>@foreach($selectorOptions['batches'] ?? [] as $batch)<option value="{{ $batch->id }}">{{ $batch->code ?: $batch->name }} - {{ $batch->program?->code }}</option>@endforeach</select>
        <select aria-label="Term" class="form-select form-select-sm" name="term_id"><option value="">Select term</option>@foreach($selectorOptions['terms'] ?? [] as $term)<option value="{{ $term->id }}">{{ $term->name }} - {{ $term->program?->code }}</option>@endforeach</select>
        <select aria-label="Subject" class="form-select form-select-sm" name="subject_id"><option value="">Select subject</option>@foreach($selectorOptions['subjects'] ?? [] as $subject)<option value="{{ $subject->id }}">{{ $subject->code ?: $subject->name }} - {{ $subject->name }}</option>@endforeach</select>
        <div class="d-flex gap-2"><input aria-label="Min Capacity" class="form-control form-control-sm" name="min_capacity" value="1"><input aria-label="Max Capacity" class="form-control form-control-sm" name="max_capacity" value="60"></div>
        <input aria-label="Current Strength" class="form-control form-control-sm" name="current_strength" value="0">
        <button class="btn btn-sm btn-primary">Create Group</button>
    </div>
</form>
<form method="POST" action="{{ route('academics.pmc.course-groups.auto-build') }}" class="card shadow-sm mt-3">@csrf
    <div class="card-header py-2 fw-semibold">Auto Build Groups</div>
    <div class="card-body vstack gap-2">
        <input aria-label="Build run title" class="form-control form-control-sm" name="title" placeholder="Build run title" value="PMC Auto Group Build">
        <input aria-label="Group prefix" class="form-control form-control-sm" name="group_prefix" placeholder="Group prefix" value="Section">
        <select aria-label="Group Type" class="form-select form-select-sm" name="group_type" required>
            @foreach(['core_section','elective_group','lab_group','tutorial_group','project_group','combined_section'] as $type)<option value="{{ $type }}">{{ str($type)->headline() }}</option>@endforeach
        </select>
        <select aria-label="Strategy" class="form-select form-select-sm" name="strategy"><option value="balanced_capacity">Balanced capacity</option><option value="choice_priority">Choice priority</option><option value="lab_capacity">Lab capacity</option></select>
        <select aria-label="Program" class="form-select form-select-sm" name="program_id"><option value="">Select program</option>@foreach($selectorOptions['programs'] ?? [] as $program)<option value="{{ $program->id }}">{{ $program->code ?: $program->name }} - {{ $program->name }}</option>@endforeach</select>
        <select aria-label="Batch" class="form-select form-select-sm" name="batch_id"><option value="">Select batch</option>@foreach($selectorOptions['batches'] ?? [] as $batch)<option value="{{ $batch->id }}">{{ $batch->code ?: $batch->name }} - {{ $batch->program?->code }}</option>@endforeach</select>
        <select aria-label="Term" class="form-select form-select-sm" name="term_id"><option value="">Select term</option>@foreach($selectorOptions['terms'] ?? [] as $term)<option value="{{ $term->id }}">{{ $term->name }} - {{ $term->program?->code }}</option>@endforeach</select>
        <select aria-label="Subject" class="form-select form-select-sm" name="subject_id" required><option value="">Select subject</option>@foreach($selectorOptions['subjects'] ?? [] as $subject)<option value="{{ $subject->id }}">{{ $subject->code ?: $subject->name }} - {{ $subject->name }}</option>@endforeach</select>
        <div class="d-flex gap-2"><input aria-label="Min Capacity" class="form-control form-control-sm" name="min_capacity" value="1"><input aria-label="Max Capacity" class="form-control form-control-sm" name="max_capacity" value="60"></div>
        <button class="btn btn-sm btn-outline-primary">Auto Build</button>
    </div>
</form>
<form method="POST" action="{{ route('academics.pmc.course-group-adjustments.store') }}" class="card shadow-sm mt-3">@csrf
    <div class="card-header py-2 fw-semibold">Group Adjustment</div>
    <div class="card-body vstack gap-2">
        <select aria-label="Course Group" class="form-select form-select-sm" name="course_group_id" required><option value="">Source group</option>@foreach($selectorOptions['courseGroups'] ?? [] as $group)<option value="{{ $group->id }}">{{ $group->name }} - {{ str($group->group_type)->headline() }}</option>@endforeach</select>
        <select aria-label="Target Course Group" class="form-select form-select-sm" name="target_course_group_id"><option value="">Target group if needed</option>@foreach($selectorOptions['courseGroups'] ?? [] as $group)<option value="{{ $group->id }}">{{ $group->name }} - {{ str($group->group_type)->headline() }}</option>@endforeach</select>
        <select aria-label="Student" class="form-select form-select-sm" name="student_id"><option value="">Student for move</option>@foreach($selectorOptions['students'] ?? [] as $student)<option value="{{ $student->id }}">{{ $student->user?->name ?? $student->enrollment_number ?? $student->roll_number ?? $student->student_id ?? 'Unassigned student' }}</option>@endforeach</select>
        <select aria-label="Adjustment Type" class="form-select form-select-sm" name="adjustment_type">
            <option value="rebalance">rebalance</option>
            <option value="split">split</option>
            <option value="merge">merge</option>
            <option value="move_student">move student</option>
            <option value="lock">lock</option>
            <option value="unlock">unlock</option>
        </select>
        <input aria-label="Strength delta" class="form-control form-control-sm" name="strength_delta" placeholder="Strength delta" value="1">
        <textarea aria-label="Adjustment reason" class="form-control form-control-sm" name="reason" placeholder="Adjustment reason" required></textarea>
        <button class="btn btn-sm btn-outline-primary">Request Adjustment</button>
    </div>
</form>
