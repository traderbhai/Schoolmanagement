<form method="POST" action="{{ route('academics.pmc.locked-slots.store') }}" class="card shadow-sm">@csrf
    <div class="card-header py-2 fw-semibold">Create Locked Slot</div>
    <div class="card-body vstack gap-2">
        <input class="form-control form-control-sm" name="title" placeholder="Slot title" required>
        <select class="form-select form-select-sm" name="slot_type"><option value="orientation">Orientation</option><option value="guest_lecture">Guest Lecture</option><option value="lab_block">Lab Block</option><option value="faculty_fixed">Faculty Fixed</option></select>
        <select class="form-select form-select-sm" name="day_of_week" required><option value="">Select day</option>@foreach([1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'] as $day => $label)<option value="{{ $day }}">{{ $label }}</option>@endforeach</select>
        <select class="form-select form-select-sm" name="timetable_slot_id" required><option value="">Select time slot</option>@foreach($selectorOptions['slots'] ?? [] as $slot)<option value="{{ $slot->id }}">{{ $slot->name }} {{ $slot->start_time }}-{{ $slot->end_time }}</option>@endforeach</select>
        <select class="form-select form-select-sm" name="course_group_id"><option value="">No specific group</option>@foreach($selectorOptions['courseGroups'] ?? [] as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</select>
        <select class="form-select form-select-sm" name="teacher_id"><option value="">No specific faculty</option>@foreach($selectorOptions['teachers'] ?? [] as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->user?->name ?? $teacher->employee_id ?? 'Unassigned faculty' }}</option>@endforeach</select>
        <select class="form-select form-select-sm" name="classroom_id"><option value="">No specific room</option>@foreach($selectorOptions['classrooms'] ?? [] as $room)<option value="{{ $room->id }}">{{ $room->name }} - {{ $room->type }} / {{ $room->capacity }}</option>@endforeach</select>
        <label class="small"><input type="checkbox" name="is_hard_lock" value="1" checked> Hard lock</label>
        <textarea class="form-control form-control-sm" name="reason" placeholder="Reason"></textarea>
        <button class="btn btn-sm btn-primary">Reserve Slot</button>
    </div>
</form>
