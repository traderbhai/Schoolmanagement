<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\CourseFeedback;
use App\Models\MentorMeeting;
use App\Models\Program;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectAnnouncement;
use App\Models\SubjectDiscussion;
use App\Models\SubjectFacultyAssignment;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AcademicCourseDeliveryService
{
    public function __construct(
        private AcademicHierarchyService $hierarchy,
        private AcademicScopeService $scopes
    ) {}

    public function dashboard(User $user): array
    {
        $load = $this->courseLoad($user);
        $sessions = $this->sessionDelivery($user);
        $attendance = $this->attendanceInterventions($user);
        $engagement = $this->courseEngagement($user);
        $mentoring = $this->mentorActions($user);

        return [
            'scopeSummary' => $this->scopeSummary($user),
            'kpis' => [
                'assigned_courses' => $load['metrics']['assigned_subjects'],
                'today_sessions' => $sessions['metrics']['today_sessions'],
                'attendance_risk' => $attendance['metrics']['attendance_risk_students'],
                'mentor_actions' => $mentoring['metrics']['open_mentor_actions'],
            ],
            'load' => $load,
            'sessions' => $sessions,
            'attendance' => $attendance,
            'engagement' => $engagement,
            'mentoring' => $mentoring,
            'reports' => $this->reports($user),
        ];
    }

    public function courseLoad(User $user): array
    {
        $subjectIds = $this->visibleSubjectIds($user);
        $programIds = $this->visibleProgramIds($user);

        $assignments = $this->applySubjectScope(
            SubjectFacultyAssignment::with(['subject.program', 'teacher.user', 'term'])->orderByDesc('is_primary'),
            $subjectIds
        )->limit(30)->get();

        $unassigned = $this->applyProgramScope(
            Subject::with('program')->where('is_active', true)->whereNotIn('id', SubjectFacultyAssignment::query()->pluck('subject_id')),
            $programIds
        )->limit(20)->get();

        return [
            'title' => 'Course Load',
            'description' => 'Assigned subjects, primary/co-faculty load, and course ownership gaps.',
            'metrics' => [
                'assigned_subjects' => $assignments->pluck('subject_id')->unique()->count(),
                'primary_assignments' => $assignments->where('is_primary', true)->count(),
                'co_faculty_assignments' => $assignments->where('is_primary', false)->count(),
                'unassigned_scoped_subjects' => $unassigned->count(),
            ],
            'items' => $assignments->map(fn (SubjectFacultyAssignment $assignment) => [
                'title' => $this->subjectLabel($assignment->subject, $assignment->subject_id),
                'subtitle' => ($assignment->subject?->program?->code ?? $assignment->program?->code ?? 'Program') . ' - ' . ($assignment->teacher?->user?->name ?? 'Faculty pending'),
                'status' => $assignment->is_primary ? 'Primary faculty' : 'Co-faculty',
                'metric_keys' => array_values(array_filter(['assigned_subjects', $assignment->is_primary ? 'primary_assignments' : 'co_faculty_assignments'])),
                'action' => route('teacher.timetable.index'),
            ])->toBase()->merge($unassigned->map(fn (Subject $subject) => [
                'title' => $subject->name,
                'subtitle' => ($subject->program?->code ?? 'Program') . ' - faculty ownership missing',
                'status' => 'Unassigned',
                'metric_keys' => ['unassigned_scoped_subjects'],
                'action' => route('chair.curriculum.assignments'),
            ]))->values(),
        ];
    }

    public function sessionDelivery(User $user): array
    {
        $subjectIds = $this->visibleSubjectIds($user);
        $programIds = $this->visibleProgramIds($user);
        $today = now()->dayOfWeekIso;

        $entries = $this->applySubjectScope(
            TimetableEntry::with(['subject.program', 'teacher.user', 'slot', 'classroom', 'version'])
                ->where('is_active', true)
                ->orderBy('day_of_week'),
            $subjectIds
        )->limit(40)->get();

        if ($subjectIds === null) {
            $entries = $this->applyProgramScope(
                TimetableEntry::with(['subject.program', 'teacher.user', 'slot', 'classroom', 'version'])
                    ->where('is_active', true)
                    ->orderBy('day_of_week'),
                $programIds
            )->limit(40)->get();
        }

        $officialEntries = $entries->filter(fn (TimetableEntry $entry) => $this->isPublishedTimetableEntry($entry));
        $todayEntries = $officialEntries->where('day_of_week', $today);
        $draftEntries = $entries->reject(fn (TimetableEntry $entry) => $this->isPublishedTimetableEntry($entry));

        return [
            'title' => 'Session Delivery',
            'description' => 'Today timetable, unpublished slots, room/faculty readiness, and delivery exceptions.',
            'metrics' => [
                'today_sessions' => $todayEntries->count(),
                'published_sessions' => $officialEntries->count(),
                'draft_sessions' => $draftEntries->count(),
                'room_pending' => $entries->whereNull('classroom_id')->count(),
            ],
            'items' => $todayEntries->map(fn (TimetableEntry $entry) => [
                'title' => $entry->subject?->name ?? 'Session #' . $entry->id,
                'subtitle' => $entry->day_name . ' - ' . ($entry->slot?->name ?? 'Slot pending') . ' - ' . ($entry->classroom?->name ?? 'Room pending'),
                'status' => ucfirst($entry->status ?? 'draft'),
                'metric_keys' => ['today_sessions', 'published_sessions'],
                'action' => route('teacher.attendance.mark'),
            ])->toBase()->merge($draftEntries->map(fn (TimetableEntry $entry) => [
                'title' => $entry->subject?->name ?? 'Session #' . $entry->id,
                'subtitle' => ($entry->subject?->program?->code ?? 'Program') . ' - timetable publish pending',
                'status' => 'Draft',
                'metric_keys' => array_values(array_filter(['draft_sessions', $entry->classroom_id ? null : 'room_pending'])),
                'action' => route('chair.timetable.builder'),
            ]))->values(),
        ];
    }

    public function attendanceInterventions(User $user): array
    {
        $studentIds = $this->visibleStudentIds($user);

        $risk = Attendance::with('student.user')
            ->selectRaw('student_id, count(*) as exception_count')
            ->whereHas('timetableEntry', fn (Builder $query) => $this->publishedTimetableScope($query))
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['absent', 'late'])
            ->groupBy('student_id')
            ->having('exception_count', '>=', 2)
            ->limit(30)
            ->get();

        $recentExceptions = Attendance::with(['student.user', 'timetableEntry.subject'])
            ->whereHas('timetableEntry', fn (Builder $query) => $this->publishedTimetableScope($query))
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['absent', 'late'])
            ->latest('date')
            ->limit(20)
            ->get();

        return [
            'title' => 'Attendance Interventions',
            'description' => 'Students requiring attendance follow-up, parent escalation, or mentoring action.',
            'metrics' => [
                'attendance_risk_students' => $risk->count(),
                'recent_exceptions' => $recentExceptions->count(),
                'active_students' => Student::whereIn('id', $studentIds)->where('status', 'active')->count(),
                'mentor_unassigned' => Student::whereIn('id', $studentIds)->whereNull('mentor_id')->count(),
            ],
            'items' => $risk->map(fn ($row) => [
                'title' => $this->studentLabel($row->student, $row->student_id),
                'subtitle' => $row->exception_count . ' absent/late records in scoped courses',
                'status' => 'Follow-up due',
                'metric_keys' => ['attendance_risk_students'],
                'action' => route('chair.students.at-risk'),
            ])->toBase()->merge($recentExceptions->map(fn (Attendance $attendance) => [
                'title' => $this->studentLabel($attendance->student, $attendance->student_id),
                'subtitle' => ($attendance->timetableEntry?->subject?->code ?? 'Subject') . ' - ' . $attendance->date?->toDateString(),
                'status' => ucfirst($attendance->status),
                'metric_keys' => ['recent_exceptions'],
                'action' => route('teacher.attendance.mark'),
            ]))->values(),
        ];
    }

    public function courseEngagement(User $user): array
    {
        $subjectIds = $this->visibleSubjectIds($user) ?? Subject::whereIn('program_id', $this->visibleProgramIds($user) ?? Program::query()->pluck('id'))->pluck('id');

        $announcements = SubjectAnnouncement::with('subject')->whereIn('subject_id', $subjectIds)->latest()->limit(20)->get();
        $discussions = SubjectDiscussion::with('subject')->whereIn('subject_id', $subjectIds)->where('is_resolved', false)->latest()->limit(20)->get();
        $lowFeedback = CourseFeedback::with('subject')
            ->selectRaw('subject_id, avg(overall_rating) as avg_rating, count(*) as response_count')
            ->whereIn('subject_id', $subjectIds)
            ->groupBy('subject_id')
            ->having('avg_rating', '<', 3.8)
            ->limit(20)
            ->get();

        return [
            'title' => 'Course Engagement',
            'description' => 'Announcements, unresolved discussions, feedback signals, and communication health.',
            'metrics' => [
                'announcements' => $announcements->count(),
                'open_discussions' => $discussions->count(),
                'low_feedback_subjects' => $lowFeedback->count(),
                'feedback_responses' => CourseFeedback::whereIn('subject_id', $subjectIds)->count(),
            ],
            'items' => $discussions->map(fn (SubjectDiscussion $discussion) => [
                'title' => $discussion->title,
                'subtitle' => ($discussion->subject?->code ?? 'Subject') . ' - unresolved discussion',
                'status' => 'Reply due',
                'metric_keys' => ['open_discussions'],
                'action' => route('student.discussions.show', [$discussion->subject_id, $discussion]),
            ])->toBase()->merge($lowFeedback->map(fn ($row) => [
                'title' => $this->subjectLabel($row->subject, $row->subject_id),
                'subtitle' => 'Average feedback ' . round((float) $row->avg_rating, 1) . ' from ' . $row->response_count . ' responses',
                'status' => 'Feedback action',
                'metric_keys' => ['low_feedback_subjects'],
                'action' => route('chair.faculty.feedback'),
            ]))->merge($announcements->map(fn (SubjectAnnouncement $announcement) => [
                'title' => $announcement->title,
                'subtitle' => ($announcement->subject?->code ?? 'Subject') . ' - announcement posted',
                'status' => $announcement->is_pinned ? 'Pinned' : 'Posted',
                'metric_keys' => ['announcements'],
                'action' => route('student.announcements.index', $announcement->subject_id),
            ]))->values(),
        ];
    }

    public function mentorActions(User $user): array
    {
        $studentIds = $this->visibleStudentIds($user);
        $meetings = MentorMeeting::with('student.user')
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['scheduled', 'pending'])
            ->orderBy('meeting_date')
            ->limit(25)
            ->get();

        $mentorStudents = Student::with('user')
            ->whereIn('id', $studentIds)
            ->where('mentor_id', $user->id)
            ->limit(25)
            ->get();

        return [
            'title' => 'Mentor Actions',
            'description' => 'Mentor meetings, student follow-ups, open notes, and wellbeing intervention queues.',
            'metrics' => [
                'open_mentor_actions' => $meetings->count(),
                'own_mentees' => $mentorStudents->count(),
                'scoped_students' => count($studentIds),
                'meetings_this_week' => MentorMeeting::whereIn('student_id', $studentIds)->whereBetween('meeting_date', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            ],
            'items' => $meetings->map(fn (MentorMeeting $meeting) => [
                'title' => $this->studentLabel($meeting->student, $meeting->student_id),
                'subtitle' => $meeting->topic . ' - ' . $meeting->meeting_date?->toDateString(),
                'status' => ucfirst($meeting->status),
                'metric_keys' => ['open_mentor_actions', 'meetings_this_week'],
                'action' => route('teacher.mentor.index'),
            ])->toBase()->merge($mentorStudents->map(fn (Student $student) => [
                'title' => $this->studentLabel($student, $student->id),
                'subtitle' => 'Assigned mentee - ' . ($student->program?->code ?? 'Program'),
                'status' => 'Mentor watch',
                'metric_keys' => ['own_mentees', 'scoped_students'],
                'action' => route('teacher.mentor.index'),
            ]))->values(),
        ];
    }

    public function reports(User $user): array
    {
        return [
            'course_load' => ['label' => 'Course load', 'count' => $this->courseLoad($user)['metrics']['assigned_subjects'], 'route' => route('academics.course-delivery.course-load')],
            'session_delivery' => ['label' => 'Session delivery', 'count' => $this->sessionDelivery($user)['metrics']['today_sessions'], 'route' => route('academics.course-delivery.session-delivery')],
            'attendance_interventions' => ['label' => 'Attendance interventions', 'count' => $this->attendanceInterventions($user)['metrics']['attendance_risk_students'], 'route' => route('academics.course-delivery.attendance-interventions')],
            'course_engagement' => ['label' => 'Course engagement', 'count' => $this->courseEngagement($user)['metrics']['open_discussions'], 'route' => route('academics.course-delivery.course-engagement')],
            'mentor_actions' => ['label' => 'Mentor actions', 'count' => $this->mentorActions($user)['metrics']['open_mentor_actions'], 'route' => route('academics.course-delivery.mentor-actions')],
        ];
    }

    public function section(User $user, string $section, array $filters = []): array
    {
        $data = match ($section) {
            'course-load' => $this->courseLoad($user),
            'session-delivery' => $this->sessionDelivery($user),
            'attendance-interventions' => $this->attendanceInterventions($user),
            'course-engagement' => $this->courseEngagement($user),
            'mentor-actions' => $this->mentorActions($user),
            default => abort(404),
        };

        $data['items'] = $this->filterItems($data['items'], $filters)->values();
        $data['filters'] = $filters;
        $data['filter_summary'] = $this->filterSummary($filters);

        return $data;
    }

    private function filterItems(Collection $items, array $filters): Collection
    {
        return $items
            ->when(! empty($filters['metric']), function (Collection $collection) use ($filters) {
                $metric = (string) $filters['metric'];

                return $collection->filter(fn (array $item) => in_array($metric, $item['metric_keys'] ?? [], true));
            })
            ->when(! empty($filters['search']), function (Collection $collection) use ($filters) {
                $search = mb_strtolower((string) $filters['search']);

                return $collection->filter(fn (array $item) => str_contains(mb_strtolower($item['title'] ?? ''), $search)
                    || str_contains(mb_strtolower($item['subtitle'] ?? ''), $search)
                    || str_contains(mb_strtolower($item['status'] ?? ''), $search));
            })
            ->when(! empty($filters['status']), function (Collection $collection) use ($filters) {
                $status = mb_strtolower((string) $filters['status']);

                return $collection->filter(fn (array $item) => mb_strtolower($item['status'] ?? '') === $status);
            });
    }

    private function filterSummary(array $filters): string
    {
        $active = collect($filters)
            ->only(['metric', 'search', 'status'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value, $key) => str($key)->headline() . ': ' . $value);

        return $active->isEmpty() ? 'Showing all scoped course-delivery records.' : $active->join(' | ');
    }

    private function visibleSubjectIds(User $user): ?Collection
    {
        if ($this->hierarchy->canSeeAll($user)) {
            return null;
        }

        $subjectIds = $this->scopes->scopeIdsFor($user, 'subject');
        $teacher = Teacher::where('user_id', $user->id)->first();
        if ($teacher) {
            $subjectIds = $subjectIds->merge(SubjectFacultyAssignment::where('teacher_id', $teacher->id)->pluck('subject_id'));
        }

        if ($subjectIds->isEmpty()) {
            $programIds = $this->visibleProgramIds($user);
            if ($programIds && $programIds->isNotEmpty()) {
                $subjectIds = Subject::whereIn('program_id', $programIds)->pluck('id');
            }
        }

        return $subjectIds->unique()->values();
    }

    private function visibleProgramIds(User $user): ?Collection
    {
        if ($this->hierarchy->canSeeAll($user)) {
            return null;
        }

        $ids = $this->scopes->scopeIdsFor($user, 'program');
        if ($ids->isEmpty()) {
            $ids = $this->scopes->scopeIdsFor($user, 'batch')
                ->map(fn ($batchId) => \App\Models\Batch::whereKey($batchId)->value('program_id'))
                ->filter()
                ->unique()
                ->values();
        }

        if ($ids->isEmpty()) {
            $subjectProgramIds = Subject::whereIn('id', $this->scopes->scopeIdsFor($user, 'subject'))->pluck('program_id')->filter();
            $ids = $ids->merge($subjectProgramIds)->unique()->values();
        }

        return $ids;
    }

    private function visibleStudentIds(User $user): array
    {
        if ($this->hierarchy->canSeeAll($user)) {
            return Student::pluck('id')->all();
        }

        $programIds = $this->visibleProgramIds($user);
        $subjectIds = $this->visibleSubjectIds($user);
        $studentIds = collect();
        $hasBroadStudentScope = $this->scopes->scopeIdsFor($user, 'program')->isNotEmpty()
            || $this->scopes->scopeIdsFor($user, 'batch')->isNotEmpty()
            || $this->scopes->scopeIdsFor($user, 'term')->isNotEmpty();

        if ($hasBroadStudentScope && $programIds !== null && $programIds->isNotEmpty()) {
            $studentIds = $studentIds->merge(Student::whereIn('program_id', $programIds)->pluck('id'));
        }

        if ($subjectIds !== null && $subjectIds->isNotEmpty()) {
            $studentIds = $studentIds->merge(
                Student::whereHas('subjectEnrollments', fn (Builder $enrollment) => $enrollment
                    ->whereIn('subject_id', $subjectIds)
                    ->where('status', 'active'))
                    ->pluck('id')
            );
        }

        $studentIds = $studentIds->merge(Student::where('mentor_id', $user->id)->pluck('id'));

        return $studentIds->unique()->values()->all();
    }

    private function applyProgramScope(Builder $query, ?Collection $programIds, string $column = 'program_id'): Builder
    {
        if ($programIds === null) {
            return $query;
        }

        if ($programIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $programIds);
    }

    private function applySubjectScope(Builder $query, ?Collection $subjectIds, string $column = 'subject_id'): Builder
    {
        if ($subjectIds === null) {
            return $query;
        }

        if ($subjectIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $subjectIds);
    }

    private function scopeSummary(User $user): array
    {
        if ($this->hierarchy->canSeeAll($user)) {
            return ['label' => 'All course-delivery records', 'detail' => 'Department-level delivery visibility'];
        }

        $scopes = $this->scopes->scopesFor($user);
        $teacher = Teacher::where('user_id', $user->id)->first();

        return [
            'label' => $teacher ? 'Faculty course scope' : ($scopes->pluck('scope_type')->unique()->map(fn ($type) => ucfirst($type))->join(', ') ?: 'Course-delivery scope'),
            'detail' => $teacher ? 'Assigned subjects and active mentees' : ($scopes->take(4)->pluck('scope_name')->join(', ') ?: 'No explicit course scope assigned yet'),
        ];
    }

    private function publishedTimetableScope(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function (Builder $versionQuery) {
                $versionQuery->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn (Builder $version) => $version->where('status', 'published'));
            });
    }

    private function isPublishedTimetableEntry(TimetableEntry $entry): bool
    {
        if (! $entry->is_active || $entry->status !== 'published') {
            return false;
        }

        if (! $entry->timetable_version_id) {
            return true;
        }

        return $entry->relationLoaded('version')
            ? $entry->version?->status === 'published'
            : $entry->version()->where('status', 'published')->exists();
    }

    private function studentLabel(?Student $student, mixed $fallbackId): string
    {
        if (! $student) {
            return 'Unassigned student';
        }

        return $student->user?->name
            ?? $student->enrollment_number
            ?? $student->roll_number
            ?? 'Student record ' . $fallbackId;
    }

    private function subjectLabel(?Subject $subject, mixed $fallbackId): string
    {
        if (! $subject) {
            return 'Unassigned subject';
        }

        return $subject->name
            ?? $subject->code
            ?? 'Subject record ' . $fallbackId;
    }
}
