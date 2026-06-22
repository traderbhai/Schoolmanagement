<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AcademicPmcTimetableGenerationItem, Student, Teacher, Department, Course, Notice, Semester, TimetableEntry, Exam, FeeStructure, FeePayment, Attendance, Placement, PlacementDrive};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index()
    {
        $currentSemester = Semester::current();

        // KPI counts
        $studentCount  = Student::where('status', 'active')->count();
        $teacherCount  = Teacher::where('status', 'active')->count();
        $deptCount     = Department::where('is_active', true)->count();
        $courseCount   = Course::where('is_active', true)->count();

        // Keep backward-compat aliases
        $stats = [
            'students'    => $studentCount,
            'teachers'    => $teacherCount,
            'departments' => $deptCount,
            'courses'     => $courseCount,
        ];
        $totalStudents    = $studentCount;
        $totalTeachers    = $teacherCount;
        $totalDepartments = $deptCount;
        $totalCourses     = $courseCount;

        // Today's attendance rate
        $todayTotal   = Attendance::whereDate('date', today())->count();
        $todayPresent = Attendance::whereDate('date', today())->whereIn('status', ['present','late'])->count();
        $todayAttendanceRate = $todayTotal > 0 ? round(($todayPresent / $todayTotal) * 100) : 0;

        // Fee totals
        $currentAcademicYearId = $currentSemester?->academic_year_id;
        $totalFeeDue = FeeStructure::when($currentAcademicYearId, fn($q) => $q->where('academic_year_id', $currentAcademicYearId))->sum('amount');
        $totalFeeCollected = FeePayment::where('status', 'paid')->sum('amount_paid');
        $feeCollectionRate = $totalFeeDue > 0 ? min(100, round(($totalFeeCollected / $totalFeeDue) * 100)) : 0;

        // Notices & semester
        $pendingNotices = Notice::active()->count();
        $recentNotices  = Notice::with('user')->active()->latest()->take(5)->get();

        // Timetable entries
        $recentEntries = $this->recentOfficialTimetableEntries($currentSemester);

        // Recent students (last 5 enrolled)
        $recentStudents = Student::with('user')->latest()->take(5)->get();

        // Recent payments (last 5)
        $recentPayments = FeePayment::with(['student.user', 'feeStructure'])->latest()->take(5)->get();

        // Placement stats
        $placedStudents = Placement::where('application_status', 'selected')->count();
        $upcomingDrives = PlacementDrive::where('status', 'upcoming')->count();

        // Upcoming exams in next 14 days
        $upcomingExams = Exam::with(['subject', 'semester'])
            ->whereBetween('exam_date', [today(), today()->addDays(14)])
            ->orderBy('exam_date')
            ->get();

        // Attendance trend — last 14 days
        $cutoff = now()->subDays(13)->toDateString();
        $today = now()->toDateString();
        $attTrendData = \App\Models\Attendance::whereBetween('date', [$cutoff, $today])
            ->selectRaw('DATE(date) as day, COUNT(*) as total, SUM(CASE WHEN status IN ("present","late") THEN 1 ELSE 0 END) as present_count')
            ->groupBy(\Illuminate\Support\Facades\DB::raw('DATE(date)'))
            ->get()
            ->keyBy('day');

        $attendanceTrend = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $data = $attTrendData->get($date);
            $total = $data?->total ?? 0;
            $present = $data?->present_count ?? 0;
            $attendanceTrend[] = [
                'date'    => now()->subDays($i)->format('d M'),
                'total'   => $total,
                'present' => $present,
                'pct'     => $total > 0 ? round(($present / $total) * 100) : 0,
            ];
        }

        // Fee collection — last 6 months
        $feeStart = now()->subMonths(5)->startOfMonth()->toDateString();
        $feeEnd = now()->endOfMonth()->toDateString();
        $feeTrendData = \App\Models\FeePayment::where('status', 'paid')
            ->whereBetween('payment_date', [$feeStart, $feeEnd])
            ->selectRaw('strftime("%Y", payment_date) as yr, strftime("%m", payment_date) as mo, SUM(amount_paid) as amount')
            ->groupBy(\Illuminate\Support\Facades\DB::raw('strftime("%Y", payment_date), strftime("%m", payment_date)'))
            ->get()
            ->keyBy(fn($r) => $r->yr . '-' . $r->mo);

        $feeCollection = [];
        $feeMonthly    = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $key = $month->format('Y') . '-' . $month->format('m');
            $entry = ['month' => $month->format('M Y'), 'amount' => (int) ($feeTrendData->get($key)?->amount ?? 0)];
            $feeCollection[] = $entry;
            $feeMonthly[]    = $entry;
        }

        // Enrollment by department
        $enrollmentByDept = Department::withCount(['students as student_count' => fn($q) => $q->where('status', 'active')])
            ->orderByDesc('student_count')
            ->get(['id', 'name', 'student_count']);

        return view('admin.dashboard', compact(
            'stats', 'recentNotices', 'currentSemester', 'recentEntries',
            'totalStudents', 'totalTeachers', 'totalDepartments', 'totalCourses',
            'studentCount', 'teacherCount', 'deptCount', 'courseCount',
            'todayAttendanceRate', 'totalFeeDue', 'totalFeeCollected', 'feeCollectionRate',
            'pendingNotices', 'recentStudents', 'recentPayments', 'upcomingExams',
            'attendanceTrend', 'feeCollection', 'feeMonthly', 'enrollmentByDept',
            'placedStudents', 'upcomingDrives'
        ));
    }

    private function recentOfficialTimetableEntries(?Semester $currentSemester): Collection
    {
        $canonicalItems = AcademicPmcTimetableGenerationItem::with([
                'subject',
                'courseGroup.subject',
                'courseGroup.program',
                'courseGroup.term',
                'program',
                'batch',
                'term',
                'teacher.user',
                'classroom',
                'slot',
                'timetableVersion',
            ])
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn (Builder $version) => $version->where('status', 'published'))
            ->when($currentSemester, function (Builder $query) use ($currentSemester) {
                $query->where(function (Builder $scope) use ($currentSemester) {
                    $scope->whereHas('term', fn (Builder $term) => $this->semesterTermScope($term, $currentSemester))
                        ->orWhereHas('courseGroup.term', fn (Builder $term) => $this->semesterTermScope($term, $currentSemester));
                });
            })
            ->latest()
            ->get();

        $canonicalProgramTermKeys = $canonicalItems
            ->map(fn (AcademicPmcTimetableGenerationItem $item) => $this->programTermKey(
                $item->program_id ?? $item->courseGroup?->program_id,
                $item->term_id ?? $item->courseGroup?->term_id
            ))
            ->unique()
            ->values();

        $legacyEntries = TimetableEntry::with(['course','subject','teacher.user','classroom','slot'])
            ->where('is_active', true)
            ->when($currentSemester, fn($q) => $q->where('semester_id', $currentSemester->id))
            ->latest()
            ->get()
            ->reject(fn (TimetableEntry $entry) => $canonicalProgramTermKeys->contains($this->programTermKey($entry->program_id, $entry->term_id)));

        return $canonicalItems
            ->map(fn (AcademicPmcTimetableGenerationItem $item) => (object) [
                'subject' => $item->subject ?: $item->courseGroup?->subject,
                'course' => (object) ['code' => $item->courseGroup?->name ?? $item->batch?->name ?? 'PMC'],
                'teacher' => $item->teacher,
                'classroom' => $item->classroom,
                'slot' => $item->slot,
                'day_of_week' => $item->day_of_week,
                'day_name' => $item->day_name,
                'created_at' => $item->updated_at ?: $item->created_at,
                'source' => 'canonical_pmc_official_sessions',
            ])
            ->merge($legacyEntries)
            ->sortByDesc(fn ($entry) => $entry->created_at)
            ->take(10)
            ->values();
    }

    private function semesterTermScope(Builder $term, Semester $semester): void
    {
        $term->where(function (Builder $scope) use ($semester) {
            $scope->where('term_number', $semester->number)
                ->orWhere('name', $semester->name);
        });
    }

    private function programTermKey(mixed $programId, mixed $termId): string
    {
        return ((string) ($programId ?? 'none')) . ':' . ((string) ($termId ?? 'none'));
    }
}
