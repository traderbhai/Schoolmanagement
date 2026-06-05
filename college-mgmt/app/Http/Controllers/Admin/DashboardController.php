<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Student, Teacher, Department, Course, Notice, Semester, TimetableEntry, Exam, FeeStructure, FeePayment, Attendance, Placement, PlacementDrive};

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
        $recentEntries = TimetableEntry::with(['course','subject','teacher.user','classroom','slot'])
            ->where('is_active', true)
            ->when($currentSemester, fn($q) => $q->where('semester_id', $currentSemester->id))
            ->latest()->take(10)->get();

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
        $attendanceTrend = [];
        for ($i = 13; $i >= 0; $i--) {
            $date    = now()->subDays($i)->toDateString();
            $total   = Attendance::whereDate('date', $date)->count();
            $present = Attendance::whereDate('date', $date)->whereIn('status', ['present','late'])->count();
            $attendanceTrend[] = [
                'date'    => now()->subDays($i)->format('d M'),
                'total'   => $total,
                'present' => $present,
                'pct'     => $total > 0 ? round(($present / $total) * 100) : 0,
            ];
        }

        // Fee collection — last 6 months
        $feeCollection = [];
        $feeMonthly    = [];
        for ($i = 5; $i >= 0; $i--) {
            $month  = now()->subMonths($i);
            $amount = FeePayment::whereYear('payment_date', $month->year)
                ->whereMonth('payment_date', $month->month)
                ->where('status', 'paid')
                ->sum('amount_paid');
            $feeCollection[] = ['month' => $month->format('M Y'), 'amount' => (int) $amount];
            $feeMonthly[]    = ['month' => $month->format('M Y'), 'amount' => (int) $amount];
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
}
