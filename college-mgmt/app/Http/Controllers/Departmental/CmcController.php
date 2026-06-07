<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\PlacementDrive;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Program;
use Illuminate\Http\Request;

class CmcController extends Controller
{
    public function dashboard()
    {
        $activeDrives = PlacementDrive::where('status', 'open')
            ->orWhere('status', 'active')
            ->count();

        $totalPlacements = Placement::where('status', 'selected')->count();

        $totalStudents = Student::count();

        $recentDrives = PlacementDrive::with('company')->latest()->take(5)->get();

        $programs = Program::where('is_active', true)->orderBy('name')->get();

        return view('departmental.cmc.dashboard', compact(
            'activeDrives', 'totalPlacements', 'totalStudents',
            'recentDrives', 'programs'
        ));
    }

    public function drives()
    {
        $drives = PlacementDrive::with(['placements', 'company'])->latest()->paginate(20);

        return view('departmental.cmc.drives', compact('drives'));
    }

    public function placements()
    {
        $placements = Placement::with(['student.user', 'drive.company'])
            ->where('application_status', 'selected')
            ->latest()
            ->paginate(30);

        return view('departmental.cmc.placements', compact('placements'));
    }

    public function analytics()
    {
        $byProgram = Placement::join('students', 'placements.student_id', '=', 'students.id')
            ->join('programs', 'students.program_id', '=', 'programs.id')
            ->where('placements.status', 'selected')
            ->selectRaw('programs.name as program_name, COUNT(*) as placed_count')
            ->groupBy('programs.id', 'programs.name')
            ->orderByDesc('placed_count')
            ->get();

        $driveStats = PlacementDrive::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return view('departmental.cmc.analytics', compact('byProgram', 'driveStats'));
    }
}
