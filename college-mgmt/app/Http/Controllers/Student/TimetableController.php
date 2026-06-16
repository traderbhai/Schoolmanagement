<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\TimetableEntry;

class TimetableController extends Controller
{
    public function index()
    {
        $student = Student::where('user_id', auth()->id())->first();

        if (!$student) {
            return view('student.timetable', ['entries' => collect(), 'days' => [], 'student' => null]);
        }

        $canonicalSubjectIds = StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->when($student->current_term_id, fn($q) => $q->where('term_id', $student->current_term_id))
            ->pluck('subject_id');

        $legacySubjectIds = Enrollment::where('student_id', $student->id)
            ->whereIn('status', ['active', 'enrolled'])
            ->when($student->current_term_id, fn($q) => $q->where('term_id', $student->current_term_id))
            ->pluck('subject_id');

        $enrolledSubjectIds = $canonicalSubjectIds
            ->merge($legacySubjectIds)
            ->unique()
            ->values();

        $entries = TimetableEntry::whereIn('subject_id', $enrolledSubjectIds)
            ->where('is_active', true)
            ->when($student->program_id, fn($q) => $q->where(function ($query) use ($student) {
                $query->whereNull('program_id')->orWhere('program_id', $student->program_id);
            }))
            ->when($student->batch_id, fn($q) => $q->where(function ($query) use ($student) {
                $query->whereNull('batch_id')->orWhere('batch_id', $student->batch_id);
            }))
            ->when($student->current_term_id, fn($q) => $q->where(function ($query) use ($student) {
                $query->whereNull('term_id')->orWhere('term_id', $student->current_term_id);
            }))
            ->with(['subject', 'classroom', 'teacher.user', 'slot'])
            ->orderBy('day_of_week')
            ->get()
            ->sortBy(fn($e) => (($e->day_of_week ?? 7) * 1000) + (optional($e->slot)->sort_order ?? 0))
            ->groupBy(fn($entry) => $entry->day_name);

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return view('student.timetable', compact('entries', 'days', 'student'));
    }
}
