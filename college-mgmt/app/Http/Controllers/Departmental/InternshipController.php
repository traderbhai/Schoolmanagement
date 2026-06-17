<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{Internship, Student, Company};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InternshipController extends Controller
{
    public function index(Request $request)
    {
        $query = Internship::with(['student.user', 'company'])->latest();
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('type'))   $query->where('type', $request->type);
        $internships = $query->paginate(20)->withQueryString();

        $ongoingCount = Internship::where('status', 'ongoing')->count();
        $completedCount = Internship::where('status', 'completed')->count();
        $overdueCount = Internship::where('status', 'ongoing')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now()->startOfDay())
            ->count();
        $activeStudents = Student::where('status', 'active')->count();

        $internshipPriority = $this->internshipPriority($overdueCount, $ongoingCount, $activeStudents);

        return view('departmental.internships.index', compact(
            'internships', 'ongoingCount', 'completedCount', 'overdueCount', 'internshipPriority'
        ));
    }

    public function create()
    {
        $students  = Student::with('user')->where('status', 'active')->get();
        $companies = Company::orderBy('name')->get();
        return view('departmental.internships.create', compact('students', 'companies'));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'student_id'       => 'required|exists:students,id',
            'company_name'     => 'required|string|max:255',
            'company_id'       => 'nullable|exists:companies,id',
            'role_title'       => 'required|string|max:255',
            'start_date'       => 'required|date',
            'end_date'         => 'nullable|date|after:start_date',
            'type'             => 'required|in:internship,industrial_training,live_project',
            'stipend'          => 'nullable|numeric|min:0',
            'supervisor_name'  => 'nullable|string|max:255',
            'supervisor_email' => 'nullable|email',
            'description'      => 'nullable|string',
        ]);

        $student = Student::findOrFail($v['student_id']);
        if ($student->status !== 'active') {
            return back()
                ->withErrors(['student_id' => 'Internships can be registered only for active students.'])
                ->withInput();
        }

        Internship::create(array_merge($v, ['status' => 'ongoing', 'approved_by' => auth()->id()]));
        return redirect()->route('cmc.internships.index')->with('success', 'Internship registered.');
    }

    public function show(Internship $internship)
    {
        $internship->load(['student.user', 'company', 'approvedBy']);
        return view('departmental.internships.show', compact('internship'));
    }

    public function complete(Request $request, Internship $internship)
    {
        $request->validate([
            'feedback' => 'nullable|string',
            'rating'   => 'nullable|integer|min:1|max:5',
            'end_date' => 'required|date',
        ]);

        if ($internship->status !== 'ongoing') {
            return back()->with('error', 'Only ongoing internships can be marked as completed.');
        }

        if (Carbon::parse($request->end_date)->lt($internship->start_date->copy()->startOfDay())) {
            return back()->withErrors(['end_date' => 'End date cannot be before the internship start date.'])->withInput();
        }

        $internship->update([
            'status'   => 'completed',
            'end_date' => $request->end_date,
            'feedback' => $request->feedback,
            'rating'   => $request->rating,
        ]);
        return back()->with('success', 'Internship marked as completed.');
    }

    private function internshipPriority(int $overdueCount, int $ongoingCount, int $activeStudents): array
    {
        if ($overdueCount > 0) {
            return [
                'level' => 'danger',
                'title' => "Close {$overdueCount} overdue internship" . ($overdueCount === 1 ? '' : 's'),
                'body' => 'Some ongoing internships have passed their planned end date. Collect feedback and close records.',
                'route' => route('cmc.internships.index', ['status' => 'ongoing']),
                'action' => 'Review Ongoing',
            ];
        }

        if ($ongoingCount > 0) {
            return [
                'level' => 'info',
                'title' => "Monitor {$ongoingCount} ongoing internship" . ($ongoingCount === 1 ? '' : 's'),
                'body' => 'Track supervisor details, planned end dates, and completion feedback for active internships.',
                'route' => route('cmc.internships.index', ['status' => 'ongoing']),
                'action' => 'View Ongoing',
            ];
        }

        if ($activeStudents > 0) {
            return [
                'level' => 'warning',
                'title' => 'Register internship activity',
                'body' => 'No ongoing internships are recorded. Add internships, industrial training, or live projects for active students.',
                'route' => route('cmc.internships.create'),
                'action' => 'Register Internship',
            ];
        }

        return [
            'level' => 'none',
            'title' => 'No student internship action today',
            'body' => 'Internship tracking will become active once student profiles and opportunities are available.',
            'route' => route('cmc.dashboard'),
            'action' => 'Back to CMC',
        ];
    }
}
