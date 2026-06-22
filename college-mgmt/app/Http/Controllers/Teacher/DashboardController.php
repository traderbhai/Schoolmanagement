<?php
namespace App\Http\Controllers\Teacher;
use App\Http\Controllers\Controller;
use App\Models\{AcademicPmcTimetableGenerationItem, Assignment, AssignmentSubmission, Semester, Notice, TimetableEntry, TimetableSlot};
use App\Services\TimetableService;
use Illuminate\Database\Eloquent\Builder;

class DashboardController extends Controller
{
    public function __construct(private TimetableService $service) {}

    public function index() {
        $user = auth()->user()->load('teacher.department');
        $teacher = $user->teacher;
        if (!$teacher) {
            $currentSemester = Semester::current();
            $notices = Notice::active()
                ->where(fn($q) => $q->where('audience','all')->orWhere('audience','teachers'))
                ->latest()
                ->take(5)
                ->get();
            $slots = TimetableSlot::where('is_active',true)->orderBy('sort_order')->get();
            $grid = [];
            $todayClasses = [];
            $weeklyLoad = 0;
            $activeAssignments = 0;
            $pendingGrading = 0;

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
            ))->with('warning', 'Your teacher profile is not linked yet. Contact administration to complete your staff profile.');
        }

        $currentSemester = Semester::current();
        $notices = Notice::active()->where(fn($q) => $q->where('audience','all')->orWhere('audience','teachers'))->latest()->take(5)->get();
        $slots = TimetableSlot::where('is_active',true)->orderBy('sort_order')->get();
        $entries = $currentSemester ? $this->publishedTimetableSessions($teacher->id, $currentSemester) : collect();
        $grid = $this->weeklyGridFromEntries($entries);
        $weeklyLoad = $entries->sum(fn ($entry) => max(1, (int) ($entry->duration_slots ?? 1)));

        $todayDay = now()->dayOfWeekIso;
        $todayClasses = collect($grid[$todayDay] ?? [])
            ->flatMap(fn ($cellEntries) => $cellEntries)
            ->values()
            ->all();

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

    private function publishedTimetableSessions(int $teacherId, Semester $semester)
    {
        $officialPmcItems = AcademicPmcTimetableGenerationItem::with(['subject', 'courseGroup.subject', 'classroom', 'slot', 'batch', 'term', 'timetableVersion'])
            ->where('teacher_id', $teacherId)
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn (Builder $version) => $version->where('status', 'published'))
            ->where(function (Builder $query) use ($semester) {
                $query->whereDoesntHave('term')
                    ->orWhereHas('term', fn (Builder $term) => $term->where('term_number', $semester->number));
            })
            ->orderBy('day_of_week')
            ->orderBy('timetable_slot_id')
            ->get();

        if ($officialPmcItems->isNotEmpty()) {
            return $officialPmcItems;
        }

        return $this->publishedTimetableEntries($teacherId, $semester->id);
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
            $grid[$entry->day_of_week][$entry->timetable_slot_id] ??= collect();
            $grid[$entry->day_of_week][$entry->timetable_slot_id]->push($entry);
        }

        return $grid;
    }
}
