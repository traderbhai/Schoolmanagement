<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\Semester;
use App\Models\Course;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Classroom;
use App\Services\TimetableService;
use Illuminate\Http\Request;

class TimetableController extends Controller
{
    public function __construct(private TimetableService $service) {}

    public function index(Request $request)
    {
        $this->authorizeAcademicScheduling();

        $semesters = Semester::with('academicYear')->orderByDesc('id')->get();
        $courses = Course::where('is_active', true)->get();
        $currentSemester = Semester::current() ?? $semesters->first();

        $semesterId = $request->semester_id ?? optional($currentSemester)->id;
        $courseId = $request->course_id;

        $slots = TimetableSlot::where('is_active', true)->orderBy('sort_order')->get();
        $grid = $semesterId ? $this->service->buildWeeklyGrid($semesterId, $courseId, officialOnly: true) : [];

        return view('admin.timetable.index', compact(
            'semesters', 'courses', 'slots', 'grid', 'semesterId', 'courseId'
        ));
    }

    public function create()
    {
        $this->authorizeAcademicScheduling();

        $semesters = Semester::with('academicYear')->get();
        $courses = Course::where('is_active', true)->get();
        $subjects = Subject::where('is_active', true)->get();
        $teachers = Teacher::with('user')->where('status', 'active')->get();
        $classrooms = Classroom::where('is_active', true)->get();
        $slots = TimetableSlot::where('is_active', true)->where('is_break', false)->orderBy('sort_order')->get();

        return view('admin.timetable.create', compact(
            'semesters', 'courses', 'subjects', 'teachers', 'classrooms', 'slots'
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeAcademicScheduling();

        $data = $request->validate([
            'semester_id'       => 'required|exists:semesters,id',
            'course_id'         => 'required|exists:courses,id',
            'subject_id'        => 'required|exists:subjects,id',
            'teacher_id'        => 'required|exists:teachers,id',
            'classroom_id'      => 'required|exists:classrooms,id',
            'timetable_slot_id' => 'required|exists:timetable_slots,id',
            'day_of_week'       => 'required|integer|min:1|max:6',
        ]);

        $conflicts = $this->service->checkConflicts($data);

        if (!empty($conflicts)) {
            return back()->withInput()->withErrors(['conflict' => $conflicts]);
        }

        TimetableEntry::create($data);

        return redirect()->route('admin.timetable.index', [
            'semester_id' => $data['semester_id'],
            'course_id'   => $data['course_id'],
        ])->with('success', 'Timetable entry added successfully.');
    }

    public function show(string $id)
    {
        $this->authorizeAcademicScheduling();

        $entry = TimetableEntry::with(['semester', 'course', 'subject', 'teacher.user', 'classroom', 'slot'])->findOrFail($id);
        return view('admin.timetable.show', compact('entry'));
    }

    public function edit(string $id)
    {
        $this->authorizeAcademicScheduling();

        $entry = TimetableEntry::findOrFail($id);
        $semesters = Semester::with('academicYear')->get();
        $courses = Course::where('is_active', true)->get();
        $subjects = Subject::where('is_active', true)->get();
        $teachers = Teacher::with('user')->where('status', 'active')->get();
        $classrooms = Classroom::where('is_active', true)->get();
        $slots = TimetableSlot::where('is_active', true)->where('is_break', false)->orderBy('sort_order')->get();

        return view('admin.timetable.edit', compact(
            'entry', 'semesters', 'courses', 'subjects', 'teachers', 'classrooms', 'slots'
        ));
    }

    public function update(Request $request, string $id)
    {
        $this->authorizeAcademicScheduling();

        $entry = TimetableEntry::findOrFail($id);

        if ($this->entryHasOperationalHistory($entry) || $entry->status === 'published') {
            return redirect()
                ->route('admin.timetable.show', $entry)
                ->with('error', 'Timetable entries with attendance, substitution, or published history cannot be structurally changed. Create a revision instead.');
        }

        $data = $request->validate([
            'semester_id'       => 'required|exists:semesters,id',
            'course_id'         => 'required|exists:courses,id',
            'subject_id'        => 'required|exists:subjects,id',
            'teacher_id'        => 'required|exists:teachers,id',
            'classroom_id'      => 'required|exists:classrooms,id',
            'timetable_slot_id' => 'required|exists:timetable_slots,id',
            'day_of_week'       => 'required|integer|min:1|max:6',
        ]);

        $conflicts = $this->service->checkConflicts($data, $entry->id);

        if (!empty($conflicts)) {
            return back()->withInput()->withErrors(['conflict' => $conflicts]);
        }

        $entry->update($data);

        return redirect()->route('admin.timetable.index', [
            'semester_id' => $data['semester_id'],
        ])->with('success', 'Timetable entry updated.');
    }

    public function destroy(string $id)
    {
        $this->authorizeAcademicScheduling();

        $entry = TimetableEntry::findOrFail($id);

        if ($this->entryHasOperationalHistory($entry) || $entry->status === 'published') {
            $entry->update([
                'is_active' => false,
                'status' => $entry->status === 'published' ? 'archived' : $entry->status,
            ]);

            return back()->with('error', 'Timetable entry has attendance, substitution, or published history and was archived instead of deleted.');
        }

        $entry->delete();
        return back()->with('success', 'Entry removed from timetable.');
    }

    private function entryHasOperationalHistory(TimetableEntry $entry): bool
    {
        return $entry->attendances()->exists()
            || $entry->substitutions()->exists()
            || (bool) $entry->timetable_version_id;
    }

    public function teacherView(Request $request)
    {
        $this->authorizeAcademicScheduling();

        $teachers = Teacher::with('user')->where('status', 'active')->get();
        $semesters = Semester::with('academicYear')->get();
        $semesterId = $request->semester_id ?? optional(Semester::current())->id;
        $teacherId = $request->teacher_id;

        $slots = TimetableSlot::where('is_active', true)->orderBy('sort_order')->get();
        $grid = ($semesterId && $teacherId) ? $this->service->buildWeeklyGrid($semesterId, null, $teacherId, officialOnly: true) : [];

        return view('admin.timetable.teacher-view', compact('teachers', 'semesters', 'slots', 'grid', 'semesterId', 'teacherId'));
    }

    private function authorizeAcademicScheduling(): void
    {
        abort_unless(auth()->user() && AccessControl::canManageAcademicScheduling(auth()->user()), 403);
    }
}
