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
        $placed = Placement::distinct('student_id')->count('student_id');
        $placementRate = $totalStudents > 0 ? round(($placed / $totalStudents) * 100, 1) : 0;

        // Salary stats
        try {
            $avgSalary = Placement::where('salary', '>', 0)->avg('salary');
            $maxSalary = Placement::max('salary');
        } catch (\Exception $e) {
            $avgSalary = null;
            $maxSalary = null;
        }

        // Active drives
        $activedrives = PlacementDrive::where('status', 'active')->count();

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
                ->distinct('student_id')->count('student_id');
            $prog->student_count  = $students;
            $prog->placed_count   = $placed;
            $prog->placement_pct  = $students > 0 ? round(($placed / $students) * 100, 1) : 0;
            return $prog;
        });

        // Top recruiters
        $topRecruiters = Placement::select('company_id')
            ->with('company')
            ->whereNotNull('company_id')
            ->get()
            ->groupBy('company_id')
            ->map(fn($g) => ['company' => $g->first()->company, 'count' => $g->count()])
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
