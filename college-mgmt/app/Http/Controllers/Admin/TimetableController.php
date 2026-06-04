<?php

namespace App\Http\Controllers\Admin;

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
        $semesters = Semester::with('academicYear')->orderByDesc('id')->get();
        $courses = Course::where('is_active', true)->get();
        $currentSemester = Semester::current() ?? $semesters->first();

        $semesterId = $request->semester_id ?? optional($currentSemester)->id;
        $courseId = $request->course_id;

        $slots = TimetableSlot::where('is_active', true)->orderBy('sort_order')->get();
        $grid = $semesterId ? $this->service->buildWeeklyGrid($semesterId, $courseId) : [];

        return view('admin.timetable.index', compact(
            'semesters', 'courses', 'slots', 'grid', 'semesterId', 'courseId'
        ));
    }

    public function create()
    {
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
        $entry = TimetableEntry::with(['semester', 'course', 'subject', 'teacher.user', 'classroom', 'slot'])->findOrFail($id);
        return view('admin.timetable.show', compact('entry'));
    }

    public function edit(string $id)
    {
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
        $entry = TimetableEntry::findOrFail($id);

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
        TimetableEntry::findOrFail($id)->delete();
        return back()->with('success', 'Entry removed from timetable.');
    }

    public function teacherView(Request $request)
    {
        $teachers = Teacher::with('user')->where('status', 'active')->get();
        $semesters = Semester::with('academicYear')->get();
        $semesterId = $request->semester_id ?? optional(Semester::current())->id;
        $teacherId = $request->teacher_id;

        $slots = TimetableSlot::where('is_active', true)->orderBy('sort_order')->get();
        $grid = ($semesterId && $teacherId) ? $this->service->buildWeeklyGrid($semesterId, null, $teacherId) : [];

        return view('admin.timetable.teacher-view', compact('teachers', 'semesters', 'slots', 'grid', 'semesterId', 'teacherId'));
    }
}
