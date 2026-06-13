<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{Placement, PlacementDrive, Student, Program, Internship, AlumniProfile};
use Illuminate\Http\Request;

class PlacementStatsController extends Controller
{
    public function index()
    {
        $totalStudents = Student::where('status', 'active')->count();

        // Placements
        $placed = Placement::where('application_status', 'selected')
            ->distinct('student_id')
            ->count('student_id');
        $placementRate = $totalStudents > 0 ? round(($placed / $totalStudents) * 100, 1) : 0;

        // Package stats (LPA)
        $avgSalary = Placement::where('application_status', 'selected')
            ->whereNotNull('offered_package')
            ->avg('offered_package');
        $maxSalary = Placement::where('application_status', 'selected')
            ->max('offered_package');

        // Active drives
        $activedrives = PlacementDrive::whereIn('status', ['upcoming', 'ongoing'])->count();

        // Internships
        $ongoingInternships   = 0;
        $completedInternships = 0;
        try {
            $ongoingInternships   = Internship::where('status', 'ongoing')->count();
            $completedInternships = Internship::where('status', 'completed')->count();
        } catch (\Exception $e) {}

        // Alumni
        $alumniCount = 0;
        try { $alumniCount = AlumniProfile::where('is_verified', true)->count(); } catch (\Exception $e) {}

        // Program-wise placement
        $programStats = Program::where('is_active', true)->get()->map(function ($prog) {
            $students = Student::where('program_id', $prog->id)->where('status', 'active')->count();
            $placed   = Placement::whereHas('student', fn($q) => $q->where('program_id', $prog->id))
                ->where('application_status', 'selected')
                ->distinct('student_id')->count('student_id');
            $prog->student_count  = $students;
            $prog->placed_count   = $placed;
            $prog->placement_pct  = $students > 0 ? round(($placed / $students) * 100, 1) : 0;
            return $prog;
        });

        // Top recruiters
        $topRecruiters = Placement::with('drive.company')
            ->where('application_status', 'selected')
            ->get()
            ->filter(fn($placement) => $placement->drive?->company)
            ->groupBy(fn($placement) => $placement->drive->company_id)
            ->map(fn($g) => ['company' => $g->first()->drive->company, 'count' => $g->count()])
            ->sortByDesc('count')
            ->take(5)
            ->values();

        return view('departmental.placement-stats', compact(
            'totalStudents', 'placed', 'placementRate', 'avgSalary', 'maxSalary',
            'activedrives', 'ongoingInternships', 'completedInternships', 'alumniCount',
            'programStats', 'topRecruiters'
        ));
    }
}
