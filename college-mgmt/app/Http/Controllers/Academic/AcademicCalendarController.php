<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\AcademicCalendar;
use App\Models\Term;
use App\Services\AcademicAccessPolicyService;
use Illuminate\Http\Request;

class AcademicCalendarController extends Controller
{
    public function __construct(private AcademicAccessPolicyService $policy) {}

    public function index(Request $request)
    {
        $termId = $request->get('term_id');
        $query = AcademicCalendar::with('term');

        if ($termId) {
            $query->where('term_id', $termId);
        }

        $events = $query->orderBy('event_date', 'asc')->paginate(20);
        $terms = Term::select('id', 'name')->get();

        return view('academic.academic-calendars.index', compact('events', 'terms', 'termId'));
    }

    public function create()
    {
        $terms = Term::select('id', 'name')->get();
        return view('academic.academic-calendars.create', compact('terms'));
    }

    public function store(Request $request)
    {
        $this->policy->authorizeAcademicPlanning($request->user());

        $validated = $request->validate([
            'term_id' => 'required|exists:terms,id',
            'event_date' => 'required|date',
            'event_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_type' => 'required|in:holiday,exam_week,semester_start,semester_end,class_start,class_end,other',
            'is_holiday' => 'boolean',
        ]);

        AcademicCalendar::create($validated);

        return redirect()->route('academic.academic-calendars.index')
            ->with('success', 'Calendar event created successfully');
    }

    public function show(AcademicCalendar $academicCalendar)
    {
        $academicCalendar->load('term');
        return view('academic.academic-calendars.show', compact('academicCalendar'));
    }

    public function edit(AcademicCalendar $academicCalendar)
    {
        $terms = Term::select('id', 'name')->get();
        return view('academic.academic-calendars.edit', compact('academicCalendar', 'terms'));
    }

    public function update(Request $request, AcademicCalendar $academicCalendar)
    {
        $this->policy->authorizeAcademicPlanning($request->user());

        $validated = $request->validate([
            'event_date' => 'required|date',
            'event_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_type' => 'required|in:holiday,exam_week,semester_start,semester_end,class_start,class_end,other',
            'is_holiday' => 'boolean',
        ]);

        $academicCalendar->update($validated);

        return redirect()->route('academic.academic-calendars.show', $academicCalendar)
            ->with('success', 'Calendar event updated successfully');
    }

    public function destroy(Request $request, AcademicCalendar $academicCalendar)
    {
        $this->policy->authorizeAcademicPlanning($request->user());

        $academicCalendar->delete();
        return redirect()->route('academic.academic-calendars.index')
            ->with('success', 'Calendar event deleted successfully');
    }

    public function getEvents(Request $request)
    {
        $termId = $request->get('term_id');
        $events = AcademicCalendar::where('term_id', $termId)
            ->orderBy('event_date', 'asc')
            ->get();

        return response()->json($events);
    }
}
