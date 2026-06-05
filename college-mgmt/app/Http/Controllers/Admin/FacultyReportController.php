<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\LeaveApplication;
use Illuminate\Support\Carbon;

class FacultyReportController extends Controller
{
    public function workload()
    {
        $teachers = Teacher::with(['user', 'department', 'timetableEntries.subject', 'timetableEntries.course'])
            ->get()
            ->map(function ($teacher) {
                $activeEntries = $teacher->timetableEntries->where('is_active', true);

                $weeklyPeriods = $activeEntries->count();

                // distinct students enrolled in courses this teacher teaches
                $courseIds = $activeEntries->pluck('course_id')->unique()->filter();
                $totalStudents = \App\Models\Enrollment::whereIn('course_id', $courseIds)
                    ->distinct('student_id')
                    ->count('student_id');

                $pendingLeaves = $teacher->leaveApplications()->where('status', 'pending')->count();

                $approvedLeavesThisMonth = $teacher->leaveApplications()
                    ->where('status', 'approved')
                    ->whereMonth('approved_at', Carbon::now()->month)
                    ->whereYear('approved_at', Carbon::now()->year)
                    ->count();

                $teacher->weekly_periods          = $weeklyPeriods;
                $teacher->total_students          = $totalStudents;
                $teacher->pending_leaves          = $pendingLeaves;
                $teacher->approved_leaves_month   = $approvedLeavesThisMonth;

                return $teacher;
            })
            ->sortByDesc('weekly_periods')
            ->values();

        return view('admin.faculty.workload', compact('teachers'));
    }
}
