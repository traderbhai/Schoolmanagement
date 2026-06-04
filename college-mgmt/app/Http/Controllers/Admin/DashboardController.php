<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Student, Teacher, Department, Course, Notice, Semester, TimetableEntry};

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'students'    => Student::where('status', 'active')->count(),
            'teachers'    => Teacher::where('status', 'active')->count(),
            'departments' => Department::where('is_active', true)->count(),
            'courses'     => Course::where('is_active', true)->count(),
        ];
        $recentNotices = Notice::with('user')->active()->latest()->take(5)->get();
        $currentSemester = Semester::current();
        $recentEntries = TimetableEntry::with(['course','subject','teacher.user','classroom','slot'])
            ->where('is_active', true)
            ->when($currentSemester, fn($q) => $q->where('semester_id', $currentSemester->id))
            ->latest()->take(10)->get();

        // Quick stat aliases for the view
        $totalStudents    = $stats['students'];
        $totalTeachers    = $stats['teachers'];
        $totalDepartments = $stats['departments'];
        $totalCourses     = $stats['courses'];

        // Attendance trend — last 14 days
        $attendanceTrend = [];
        for ($i = 13; $i >= 0; $i--) {
            $date    = now()->subDays($i)->toDateString();
            $total   = \App\Models\Attendance::whereDate('date', $date)->count();
            $present = \App\Models\Attendance::whereDate('date', $date)->whereIn('status', ['present','late'])->count();
            $attendanceTrend[] = [
                'date'    => now()->subDays($i)->format('d M'),
                'total'   => $total,
                'present' => $present,
                'pct'     => $total > 0 ? round(($present / $total) * 100) : 0,
            ];
        }

        // Fee collection — last 6 months
        $feeCollection = [];
        for ($i = 5; $i >= 0; $i--) {
            $month  = now()->subMonths($i);
            $amount = \App\Models\FeePayment::whereYear('payment_date', $month->year)
                ->whereMonth('payment_date', $month->month)
                ->where('status', 'paid')
                ->sum('amount_paid');
            $feeCollection[] = [
                'month'  => $month->format('M Y'),
                'amount' => (int) $amount,
            ];
        }

        // Enrollment by department
        $enrollmentByDept = \App\Models\Department::withCount(['students as student_count' => fn($q) => $q->where('status', 'active')])
            ->orderByDesc('student_count')
            ->get(['id', 'name', 'student_count']);

        return view('admin.dashboard', compact(
            'stats', 'recentNotices', 'currentSemester', 'recentEntries',
            'totalStudents', 'totalTeachers', 'totalDepartments', 'totalCourses',
            'attendanceTrend', 'feeCollection', 'enrollmentByDept'
        ));
    }
}
