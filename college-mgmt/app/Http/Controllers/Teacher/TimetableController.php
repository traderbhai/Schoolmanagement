<?php
namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{Term, TimetableSlot, TimetableEntry, TimetableSubstitution};
use App\Services\TimetableService;

class TimetableController extends Controller
{
    public function index()
    {
        $teacher = auth()->user()->teacher;
        if (!$teacher) abort(403);

        $currentTerm = Term::latest('start_date')->first();
        $slots = TimetableSlot::where('is_active', true)->orderBy('sort_order')->get();
        $days  = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday'];

        $entries = TimetableEntry::where('teacher_id', $teacher->id)
            ->where('term_id', $currentTerm?->id)
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function ($query) {
                $query->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn($version) => $version->where('status', 'published'));
            })
            ->with(['subject', 'classroom', 'slot', 'batch'])
            ->get()
            ->keyBy(fn($e) => $e->day_of_week . '-' . $e->timetable_slot_id);

        // Today's substitutions (cancellations/replacements for this teacher)
        $todaySubstitutions = TimetableSubstitution::whereHas('entry', fn($q) =>
            $q->where('teacher_id', $teacher->id))
            ->where('date', today()->toDateString())
            ->with('entry.subject')
            ->get();

        return view('teacher.timetable.index', compact(
            'slots', 'days', 'entries', 'currentTerm', 'todaySubstitutions'
        ));
    }
}
