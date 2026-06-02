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

        return view('admin.dashboard', compact('stats', 'recentNotices', 'currentSemester', 'recentEntries'));
    }
}
