<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{Exam, ExamResult, Semester, Subject, Classroom, Student};
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function index(Request $request) {
        $exams = Exam::with(['semester','subject'])
            ->when($request->semester_id, fn($q,$v) => $q->where('semester_id',$v))
            ->latest()->paginate(20);
        $semesters = Semester::with('academicYear')->get();
        return view('admin.exams.index', compact('exams','semesters'));
    }
    public function create() {
        $semesters = Semester::with('academicYear')->get();
        $subjects = Subject::where('is_active',true)->get();
        $classrooms = Classroom::where('is_active',true)->get();
        return view('admin.exams.create', compact('semesters','subjects','classrooms'));
    }
    public function store(Request $request) {
        $data = $request->validate([
            'semester_id'   => 'required|exists:semesters,id',
            'subject_id'    => 'required|exists:subjects,id',
            'name'          => 'required|string|max:255',
            'type'          => 'required|in:internal,midterm,final,practical,assignment',
            'exam_date'     => 'required|date',
            'start_time'    => 'nullable|date_format:H:i',
            'end_time'      => 'nullable|date_format:H:i|after:start_time',
            'total_marks'   => 'required|integer|min:1',
            'passing_marks' => 'required|integer|min:1|lte:total_marks',
            'classroom_id'  => 'nullable|exists:classrooms,id',
        ]);
        Exam::create($data);
        return redirect()->route('admin.exams.index')->with('success', 'Exam scheduled.');
    }
    public function show(Exam $exam) {
        $exam->load(['semester','subject','classroom','results.student.user']);
        return view('admin.exams.show', compact('exam'));
    }
    public function edit(Exam $exam) {
        $semesters = Semester::with('academicYear')->get();
        $subjects = Subject::where('is_active',true)->get();
        $classrooms = Classroom::where('is_active',true)->get();
        return view('admin.exams.edit', compact('exam','semesters','subjects','classrooms'));
    }
    public function update(Request $request, Exam $exam) {
        $data = $request->validate([
            'semester_id'   => 'required|exists:semesters,id',
            'subject_id'    => 'required|exists:subjects,id',
            'name'          => 'required|string|max:255',
            'type'          => 'required|in:internal,midterm,final,practical,assignment',
            'exam_date'     => 'required|date',
            'total_marks'   => 'required|integer|min:1',
            'passing_marks' => 'required|integer|min:1|lte:total_marks',
        ]);
        $exam->update($data);
        return redirect()->route('admin.exams.show', $exam)->with('success', 'Exam updated.');
    }
    public function destroy(Exam $exam) {
        $exam->delete();
        return redirect()->route('admin.exams.index')->with('success', 'Deleted.');
    }

    public function enterResults(Exam $exam) {
        $exam->load(['subject','semester']);
        $students = Student::whereHas('enrollments', fn($q) =>
            $q->where('semester_id', $exam->semester_id)->where('subject_id', $exam->subject_id)
        )->with(['user','examResults' => fn($q) => $q->where('exam_id',$exam->id)])->get();
        return view('admin.exams.results', compact('exam','students'));
    }

    public function saveResults(Request $request, Exam $exam) {
        $request->validate(['results' => 'required|array']);
        foreach ($request->results as $studentId => $result) {
            ExamResult::updateOrCreate(
                ['exam_id' => $exam->id, 'student_id' => $studentId],
                [
                    'marks_obtained' => $result['is_absent'] ?? false ? null : ($result['marks'] ?? null),
                    'grade'          => $result['grade'] ?? null,
                    'is_absent'      => $result['is_absent'] ?? false,
                    'remarks'        => $result['remarks'] ?? null,
                ]
            );
        }
        return redirect()->route('admin.exams.show', $exam)->with('success', 'Results saved.');
    }
}
