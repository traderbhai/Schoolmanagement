<?php
namespace App\Http\Controllers\Teacher;
use App\Http\Controllers\Controller;
use App\Models\{Assignment, AssignmentSubmission, Semester, Notice, TimetableEntry, TimetableSlot};
use App\Services\TimetableService;

class DashboardController extends Controller
{
    public function __construct(private TimetableService $service) {}

    public function index() {
        $user = auth()->user()->load('teacher.department');
        $teacher = $user->teacher;
        if (!$teacher) return redirect()->route('login');

        $currentSemester = Semester::current();
        $notices = Notice::active()->where(fn($q) => $q->where('audience','all')->orWhere('audience','teachers'))->latest()->take(5)->get();
        $slots = TimetableSlot::where('is_active',true)->orderBy('sort_order')->get();
        $entries = $currentSemester ? $this->publishedTimetableEntries($teacher->id, $currentSemester->id) : collect();
        $grid = $this->weeklyGridFromEntries($entries);
        $weeklyLoad = $entries->count();

        $todayDay = now()->dayOfWeekIso;
        $todayClasses = isset($grid[$todayDay]) ? array_filter($grid[$todayDay]) : [];

        $activeAssignments = Assignment::where('created_by', $user->id)
            ->where('is_published', true)
            ->where('due_at', '>=', now())
            ->count();

        $pendingGrading = $this->pendingRosterSubmissionCount($user->id);

        return view('teacher.dashboard', compact(
            'teacher',
            'currentSemester',
            'notices',
            'slots',
            'grid',
            'todayClasses',
            'weeklyLoad',
            'activeAssignments',
            'pendingGrading'
        ));
    }

    private function publishedTimetableEntries(int $teacherId, int $semesterId)
    {
        return TimetableEntry::with(['subject', 'teacher.user', 'classroom', 'slot', 'course'])
            ->where('teacher_id', $teacherId)
            ->where('semester_id', $semesterId)
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function ($query) {
                $query->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn($version) => $version->where('status', 'published'));
            })
            ->get();
    }

    private function pendingRosterSubmissionCount(int $userId): int
    {
        return Assignment::where('created_by', $userId)
            ->get(['id', 'subject_id', 'term_id'])
            ->sum(function (Assignment $assignment) {
                return AssignmentSubmission::where('assignment_id', $assignment->id)
                    ->where('status', 'submitted')
                    ->whereHas('student', function ($studentQuery) use ($assignment) {
                        $studentQuery->whereHas('subjectEnrollments', function ($enrollment) use ($assignment) {
                            $enrollment->where('subject_id', $assignment->subject_id)
                                ->where('status', 'active')
                                ->when($assignment->term_id, fn ($termQuery) => $termQuery->where(function ($nested) use ($assignment) {
                                    $nested->whereNull('term_id')->orWhere('term_id', $assignment->term_id);
                                }));
                        })->orWhereHas('enrollments', function ($enrollment) use ($assignment) {
                            $enrollment->where('subject_id', $assignment->subject_id)
                                ->whereIn('status', ['active', 'enrolled'])
                                ->when($assignment->term_id, fn ($termQuery) => $termQuery->where(function ($nested) use ($assignment) {
                                    $nested->whereNull('term_id')->orWhere('term_id', $assignment->term_id);
                                }));
                        });
                    })
                    ->count();
            });
    }

    private function weeklyGridFromEntries($entries): array
    {
        $grid = [];
        foreach (range(1, 6) as $day) {
            $grid[$day] = [];
        }

        foreach ($entries as $entry) {
            $grid[$entry->day_of_week][$entry->timetable_slot_id] = $entry;
        }

        return $grid;
    }
}
