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
        $placementRate = 0;
        $totalStudents = Student::count();
        if ($totalStudents > 0) {
            $placed = Placement::where('application_status', 'selected')
                ->whereYear('created_at', now()->year)
                ->count();
            $placementRate = round(($placed / $totalStudents) * 100, 1);
        }

        return view('departmental.director.reports', compact('placementRate', 'totalStudents'));
    }
}
