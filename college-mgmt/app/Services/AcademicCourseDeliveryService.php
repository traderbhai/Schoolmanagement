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
                'title' => $assignment->subject?->name ?? 'Subject #' . $assignment->subject_id,
                'subtitle' => ($assignment->subject?->program?->code ?? $assignment->program?->code ?? 'Program') . ' - ' . ($assignment->teacher?->user?->name ?? 'Faculty pending'),
                'status' => $assignment->is_primary ? 'Primary faculty' : 'Co-faculty',
                'action' => route('teacher.timetable.index'),
            ])->merge($unassigned->map(fn (Subject $subject) => [
                'title' => $subject->name,
                'subtitle' => ($subject->program?->code ?? 'Program') . ' - faculty ownership missing',
                'status' => 'Unassigned',
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
            TimetableEntry::with(['subject.program', 'teacher.user', 'slot', 'classroom'])
                ->where('is_active', true)
                ->orderBy('day_of_week'),
            $subjectIds
        )->limit(40)->get();

        if ($subjectIds === null) {
            $entries = $this->applyProgramScope(
                TimetableEntry::with(['subject.program', 'teacher.user', 'slot', 'classroom'])
                    ->where('is_active', true)
                    ->orderBy('day_of_week'),
                $programIds
            )->limit(40)->get();
        }

        $todayEntries = $entries->where('day_of_week', $today);
        $draftEntries = $entries->where('status', '!=', 'published');

        return [
            'title' => 'Session Delivery',
            'description' => 'Today timetable, unpublished slots, room/faculty readiness, and delivery exceptions.',
            'metrics' => [
                'today_sessions' => $todayEntries->count(),
                'published_sessions' => $entries->where('status', 'published')->count(),
                'draft_sessions' => $draftEntries->count(),
                'room_pending' => $entries->whereNull('classroom_id')->count(),
            ],
            'items' => $todayEntries->map(fn (TimetableEntry $entry) => [
                'title' => $entry->subject?->name ?? 'Session #' . $entry->id,
                'subtitle' => $entry->day_name . ' - ' . ($entry->slot?->name ?? 'Slot pending') . ' - ' . ($entry->classroom?->name ?? 'Room pending'),
                'status' => ucfirst($entry->status ?? 'draft'),
                'action' => route('teacher.attendance.mark'),
            ])->merge($draftEntries->map(fn (TimetableEntry $entry) => [
                'title' => $entry->subject?->name ?? 'Session #' . $entry->id,
                'subtitle' => ($entry->subject?->program?->code ?? 'Program') . ' - timetable publish pending',
                'status' => 'Draft',
                'action' => route('chair.timetable.builder'),
            ]))->values(),
        ];
    }

    public function attendanceInterventions(User $user): array
    {
        $studentIds = $this->visibleStudentIds($user);

        $risk = Attendance::with('student.user')
            ->selectRaw('student_id, count(*) as exception_count')
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['absent', 'late'])
            ->groupBy('student_id')
            ->having('exception_count', '>=', 2)
            ->limit(30)
            ->get();

        $recentExceptions = Attendance::with(['student.user', 'timetableEntry.subject'])
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
                'title' => $row->student?->user?->name ?? 'Student #' . $row->student_id,
                'subtitle' => $row->exception_count . ' absent/late records in scoped courses',
                'status' => 'Follow-up due',
                'action' => route('chair.students.at-risk'),
            ])->merge($recentExceptions->map(fn (Attendance $attendance) => [
                'title' => $attendance->student?->user?->name ?? 'Student #' . $attendance->student_id,
                'subtitle' => ($attendance->timetableEntry?->subject?->code ?? 'Subject') . ' - ' . $attendance->date?->toDateString(),
                'status' => ucfirst($attendance->status),
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
                'action' => route('student.discussions.show', [$discussion->subject_id, $discussion]),
            ])->merge($lowFeedback->map(fn ($row) => [
                'title' => $row->subject?->name ?? 'Subject #' . $row->subject_id,
                'subtitle' => 'Average feedback ' . round((float) $row->avg_rating, 1) . ' from ' . $row->response_count . ' responses',
                'status' => 'Feedback action',
                'action' => route('chair.faculty.feedback'),
            ]))->merge($announcements->map(fn (SubjectAnnouncement $announcement) => [
                'title' => $announcement->title,
                'subtitle' => ($announcement->subject?->code ?? 'Subject') . ' - announcement posted',
                'status' => $announcement->is_pinned ? 'Pinned' : 'Posted',
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
                'title' => $meeting->student?->user?->name ?? 'Student #' . $meeting->student_id,
                'subtitle' => $meeting->topic . ' - ' . $meeting->meeting_date?->toDateString(),
                'status' => ucfirst($meeting->status),
                'action' => route('teacher.mentor.index'),
            ])->merge($mentorStudents->map(fn (Student $student) => [
                'title' => $student->user?->name ?? 'Student #' . $student->id,
                'subtitle' => 'Assigned mentee - ' . ($student->program?->code ?? 'Program'),
                'status' => 'Mentor watch',
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

    public function section(User $user, string $section): array
    {
        return match ($section) {
            'course-load' => $this->courseLoad($user),
            'session-delivery' => $this->sessionDelivery($user),
            'attendance-interventions' => $this->attendanceInterventions($user),
            'course-engagement' => $this->courseEngagement($user),
            'mentor-actions' => $this->mentorActions($user),
            default => abort(404),
        };
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
        $programIds = $this->visibleProgramIds($user);
        $subjectIds = $this->visibleSubjectIds($user);

        $query = Student::query();
        if ($programIds !== null && $programIds->isNotEmpty()) {
            $query->whereIn('program_id', $programIds);
        } elseif ($programIds !== null) {
            $query->whereRaw('1 = 0');
        }

        if ($subjectIds !== null && $subjectIds->isNotEmpty()) {
            $query->orWhereHas('subjectEnrollments', fn (Builder $enrollment) => $enrollment->whereIn('subject_id', $subjectIds)->where('status', 'active'));
        }

        return $query->pluck('id')->unique()->values()->all();
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
}
