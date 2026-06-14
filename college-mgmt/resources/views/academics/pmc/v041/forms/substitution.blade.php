<form method="POST" action="{{ route('academics.pmc.substitution-intelligence.recommend') }}" class="card shadow-sm mb-3">@csrf
    <div class="card-header py-2 fw-semibold">Recommend Substitute</div>
    <div class="card-body vstack gap-2">
        <input class="form-control form-control-sm" name="course_group_id" placeholder="Course group ID">
        <input class="form-control form-control-sm" name="original_teacher_id" placeholder="Original teacher ID" required>
        <input type="date" class="form-control form-control-sm" name="substitution_date" value="{{ now()->toDateString() }}">
        <button class="btn btn-sm btn-primary">Recommend</button>
    </div>
</form>
<form method="POST" action="{{ route('academics.pmc.timetable-notifications.store') }}" class="card shadow-sm">@csrf
    <div class="card-header py-2 fw-semibold">Queue Timetable Notification</div>
    <div class="card-body vstack gap-2">
        <input class="form-control form-control-sm" name="notification_type" value="timetable_revision">
        <input class="form-control form-control-sm" name="recipient_type" value="students">
        <input class="form-control form-control-sm" name="title" placeholder="Notification title" required>
        <textarea class="form-control form-control-sm" name="message" placeholder="Message"></textarea>
        <button class="btn btn-sm btn-primary">Queue Notification</button>
    </div>
</form>
