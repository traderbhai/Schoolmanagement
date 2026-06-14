<form method="POST" action="{{ route('academics.pmc.locked-slots.store') }}" class="card shadow-sm">@csrf
    <div class="card-header py-2 fw-semibold">Create Locked Slot</div>
    <div class="card-body vstack gap-2">
        <input class="form-control form-control-sm" name="title" placeholder="Slot title" required>
        <select class="form-select form-select-sm" name="slot_type"><option value="orientation">Orientation</option><option value="guest_lecture">Guest Lecture</option><option value="lab_block">Lab Block</option><option value="faculty_fixed">Faculty Fixed</option></select>
        <input class="form-control form-control-sm" name="day_of_week" placeholder="Day 1-7" required>
        <input class="form-control form-control-sm" name="timetable_slot_id" placeholder="Timetable slot ID" required>
        <input class="form-control form-control-sm" name="course_group_id" placeholder="Course group ID">
        <input class="form-control form-control-sm" name="teacher_id" placeholder="Teacher ID">
        <input class="form-control form-control-sm" name="classroom_id" placeholder="Room ID">
        <label class="small"><input type="checkbox" name="is_hard_lock" value="1" checked> Hard lock</label>
        <textarea class="form-control form-control-sm" name="reason" placeholder="Reason"></textarea>
        <button class="btn btn-sm btn-primary">Reserve Slot</button>
    </div>
</form>
