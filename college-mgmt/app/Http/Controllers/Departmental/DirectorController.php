<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Program;
use App\Models\PlacementDrive;
use App\Models\Placement;
use App\Models\User;
use Illuminate\Http\Request;

class DirectorController extends Controller
{
    public function dashboard()
    {
        $totalStudents = Student::count();
        $totalPrograms = Program::where('is_active', true)->count();
        $placedThisYear = Placement::where('application_status', 'selected')
            ->whereYear('created_at', now()->year)
            ->count();

        $totalFaculty = User::whereHas('roles', fn($q) => $q->where('name', 'teacher'))->count();

        $programs = Program::where('is_active', true)
            ->withCount('students')
            ->orderBy('students_count', 'desc')
            ->take(6)
            ->get();

        $activeDrives = PlacementDrive::where(function ($q) {
            $q->where('status', 'open')->orWhere('status', 'active');
        })->count();

        return view('departmental.director.dashboard', compact(
            'totalStudents', 'totalPrograms', 'placedThisYear',
            'totalFaculty', 'programs', 'activeDrives'
        ));
    }

    public function programs()
    {
        $programs = Program::where('is_active', true)
            ->withCount('students')
            ->orderBy('name')
            ->get();

        return view('departmental.director.programs', compact('programs'));
    }

    public function reports()
    {
        $currentYear = now()->year;
        $totalStudents = Student::count();

        // Placement metrics
        $placedThisYear = Placement::where('application_status', 'selected')
            ->whereYear('created_at', $currentYear)->count();
        $placementRate = $totalStudents > 0
            ? round(($placedThisYear / $totalStudents) * 100, 1)
            : 0;

        // Enrollment by program (top programs)
        $enrollmentByProgram = Program::where('is_active', true)
            ->withCount('students')
            ->orderBy('students_count', 'desc')
            ->get(['id', 'name', 'code']);

        // Fee collection this year
        $feeCollectedThisYear = \App\Models\FeePayment::where('status', 'paid')
            ->whereYear('payment_date', $currentYear)
            ->sum('amount_paid');

        $feeCollectedLastYear = \App\Models\FeePayment::where('status', 'paid')
            ->whereYear('payment_date', $currentYear - 1)
            ->sum('amount_paid');

        // Attendance overview
        $totalAttendance = \App\Models\Attendance::whereYear('date', $currentYear)->count();
        $presentCount    = \App\Models\Attendance::whereYear('date', $currentYear)
            ->where('status', 'present')->count();
        $overallAttendancePct = $totalAttendance > 0
            ? round(($presentCount / $totalAttendance) * 100, 1)
            : 0;

        // Active drives & total placed all time
        $activeDrives   = PlacementDrive::whereIn('status', ['open', 'active'])->count();
        $totalPlaced    = Placement::where('application_status', 'selected')->count();

        // Programs with low enrollment (< 5 students)
        $lowEnrollmentPrograms = $enrollmentByProgram->filter(fn($p) => $p->students_count < 5);

        return view('departmental.director.reports', compact(
            'totalStudents', 'placementRate', 'placedThisYear',
            'enrollmentByProgram', 'feeCollectedThisYear', 'feeCollectedLastYear',
            'overallAttendancePct', 'activeDrives', 'totalPlaced',
            'lowEnrollmentPrograms', 'currentYear'
        ));
    }
}
