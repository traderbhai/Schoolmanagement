<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\TimetableEntry;

class TimetableController extends Controller
{
    public function index()
    {
        $student = Student::where('user_id', auth()->id())->first();

        if (!$student) {
            return view('student.timetable', ['entries' => collect(), 'days' => [], 'student' => null]);
        }

        // Get subjects the student is enrolled in for current term
        $enrolledSubjectIds = Enrollment::where('student_id', $student->id)
            ->where('term_id', $student->current_term_id)
            ->pluck('subject_id');

        // Get timetable entries for those subjects
        $entries = TimetableEntry::whereIn('subject_id', $enrolledSubjectIds)
            ->with(['subject', 'classroom', 'teacher.user', 'slot'])
            ->orderByRaw("CASE day_of_week
                WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3
                WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6
                ELSE 7 END")
            ->get()
            ->sortBy(fn($e) => optional($e->slot)->sort_order ?? 0)
            ->groupBy('day_of_week');

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return view('student.timetable', compact('entries', 'days', 'student'));
    }
}
