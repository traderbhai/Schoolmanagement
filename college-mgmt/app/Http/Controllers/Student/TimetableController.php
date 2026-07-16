<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\TimetableEntry;
use App\Services\PortalAccessPolicyService;

class TimetableController extends Controller
{
    public function __construct(private PortalAccessPolicyService $portalAccess) {}

    public function index()
    {
        $this->portalAccess->authorizeStudentPortal(auth()->user());

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

        $courseGroupIds = AcademicPmcCourseGroupMember::where('student_id', $student->id)
            ->where('status', 'active')
            ->pluck('course_group_id');

        $canonicalEntries = AcademicPmcTimetableGenerationItem::query()
            ->whereIn('course_group_id', $courseGroupIds)
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn($version) => $version->where('status', 'published'))
            ->when($student->program_id, fn($q) => $q->where(function ($query) use ($student) {
                $query->whereNull('program_id')->orWhere('program_id', $student->program_id);
            }))
            ->when($student->batch_id, fn($q) => $q->where(function ($query) use ($student) {
                $query->whereNull('batch_id')->orWhere('batch_id', $student->batch_id);
            }))
            ->when($student->current_term_id, fn($q) => $q->where(function ($query) use ($student) {
                $query->whereNull('term_id')->orWhere('term_id', $student->current_term_id);
            }))
            ->with(['subject', 'courseGroup.subject', 'classroom', 'teacher.user', 'slot', 'timetableVersion'])
            ->orderBy('day_of_week')
            ->get()
            ->sortBy(fn($e) => (($e->day_of_week ?? 7) * 1000) + (optional($e->slot)->sort_order ?? 0));

        if ($canonicalEntries->isNotEmpty()) {
            $entries = $canonicalEntries->groupBy(fn($entry) => $entry->day_name);
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

            return view('student.timetable', compact('entries', 'days', 'student'));
        }

        $entries = TimetableEntry::whereIn('subject_id', $enrolledSubjectIds)
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function ($query) {
                $query->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn($version) => $version->where('status', 'published'));
            })
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
