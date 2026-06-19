<?php
namespace App\Http\Controllers\Admin;
use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\{Exam, ExamRegistration, ExamResult, Semester, Subject, Classroom, Student, StudentSubjectEnrollment, Enrollment, Term};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExamController extends Controller
{
    public function index(Request $request) {
        $this->authorizeExamManagement();

        $exams = Exam::with(['semester','subject'])
            ->when($request->semester_id, fn($q,$v) => $q->where('semester_id',$v))
            ->latest()->paginate(20);
        $semesters = Semester::with('academicYear')->get();
        return view('admin.exams.index', compact('exams','semesters'));
    }
    public function create() {
        $this->authorizeExamManagement();

        $semesters = Semester::with('academicYear')->get();
        $subjects = Subject::where('is_active',true)->get();
        $classrooms = Classroom::where('is_active',true)->get();
        return view('admin.exams.create', compact('semesters','subjects','classrooms'));
    }
    public function store(Request $request) {
        $this->authorizeExamManagement();

        $data = $request->validate([
            'semester_id'   => 'required|exists:semesters,id',
            'subject_id'    => ['required', Rule::exists('subjects', 'id')->where('is_active', true)],
            'name'          => 'required|string|max:255',
            'type'          => 'required|in:internal,midterm,final,practical,assignment',
            'exam_date'     => 'required|date',
            'start_time'    => 'nullable|date_format:H:i',
            'end_time'      => 'nullable|date_format:H:i|after:start_time',
            'total_marks'   => 'required|integer|min:1',
            'passing_marks' => 'required|integer|min:1|lte:total_marks',
            'classroom_id'  => ['nullable', Rule::exists('classrooms', 'id')->where('is_active', true)],
        ]);

        if ($this->examDateOutsideSemester($data)) {
            return back()
                ->withErrors(['exam_date' => 'Exam date must fall within the selected semester date range.'])
                ->withInput();
        }

        $data = $this->syncExamProgramFromSubject($data);

        Exam::create($data);
        return redirect()->route('admin.exams.index')->with('success', 'Exam scheduled.');
    }
    public function show(Exam $exam) {
        $this->authorizeExamManagement();

        $exam->load(['semester','subject','classroom','results.student.user']);
        return view('admin.exams.show', compact('exam'));
    }
    public function edit(Exam $exam) {
        $this->authorizeExamManagement();

        $semesters = Semester::with('academicYear')->get();
        $subjects = Subject::where('is_active',true)->get();
        $classrooms = Classroom::where('is_active',true)->get();
        return view('admin.exams.edit', compact('exam','semesters','subjects','classrooms'));
    }
    public function update(Request $request, Exam $exam) {
        $this->authorizeExamManagement();

        if ($exam->published_at) {
            return redirect()->route('admin.exams.show', $exam)
                ->with('error', 'Published exams cannot be edited because official result history is locked.');
        }

        $data = $request->validate([
            'semester_id'   => 'required|exists:semesters,id',
            'subject_id'    => ['required', Rule::exists('subjects', 'id')->where('is_active', true)],
            'name'          => 'required|string|max:255',
            'type'          => 'required|in:internal,midterm,final,practical,assignment',
            'exam_date'     => 'required|date',
            'total_marks'   => 'required|integer|min:1',
            'passing_marks' => 'required|integer|min:1|lte:total_marks',
        ]);

        if ($this->examDateOutsideSemester($data)) {
            return back()
                ->withErrors(['exam_date' => 'Exam date must fall within the selected semester date range.'])
                ->withInput();
        }

        $data = $this->syncExamProgramFromSubject($data, $exam);

        if ($this->hasExamOperationalHistory($exam) && $this->changesExamContract($exam, $data)) {
            return redirect()->route('admin.exams.show', $exam)
                ->with('error', 'Exams with result or registration history cannot have program, subject, semester, type, or marks-scale fields changed.');
        }

        $exam->update($data);
        return redirect()->route('admin.exams.show', $exam)->with('success', 'Exam updated.');
    }
    public function destroy(Exam $exam) {
        $this->authorizeExamManagement();

        if ($exam->published_at) {
            return redirect()->route('admin.exams.index')
                ->with('error', 'Published exams cannot be deleted because official result history is locked.');
        }

        if ($this->hasExamOperationalHistory($exam)) {
            return redirect()->route('admin.exams.index')
                ->with('error', 'Exams with result or registration history cannot be deleted. Archive or cancel through an audited exam workflow instead.');
        }

        $exam->delete();
        return redirect()->route('admin.exams.index')->with('success', 'Deleted.');
    }

    public function enterResults(Exam $exam) {
        $this->authorizeExamManagement();

        $exam->load(['subject','semester']);
        $students = $this->eligibleStudentsForExam($exam)
            ->with(['user','examResults' => fn($q) => $q->where('exam_id',$exam->id)])
            ->orderBy('roll_number')
            ->get();
        return view('admin.exams.results', compact('exam','students'));
    }

    public function saveResults(Request $request, Exam $exam) {
        $this->authorizeExamManagement();

        if ($exam->published_at) {
            return back()->with('error', 'Published results are locked. Use the Exam Cell appeal/correction workflow for changes.');
        }

        if ($exam->exam_date && $exam->exam_date->isFuture()) {
            return back()->with('error', 'Exam results cannot be entered before the exam date.');
        }

        $request->validate([
            'results' => 'required|array',
            'results.*.is_absent' => 'nullable|boolean',
            'results.*.marks' => 'nullable|numeric|min:0|max:' . (float) $exam->total_marks,
            'results.*.marks_obtained' => 'nullable|numeric|min:0|max:' . (float) $exam->total_marks,
            'results.*.grade' => 'nullable|string|max:10',
            'results.*.remarks' => 'nullable|string|max:500',
        ]);

        $allowedStudentIds = $this->eligibleStudentsForExam($exam)->pluck('id')->map(fn($id) => (string) $id)->all();
        $submittedStudentIds = array_map('strval', array_keys($request->results));
        abort_unless(empty(array_diff($submittedStudentIds, $allowedStudentIds)), 403, 'Results include students outside this exam roster.');

        foreach ($request->results as $studentId => $result) {
            $isAbsent = !empty($result['is_absent']);
            $marks = $result['marks_obtained'] ?? $result['marks'] ?? null;
            if (! $isAbsent && $marks === null) {
                return back()
                    ->withInput()
                    ->withErrors(["results.{$studentId}.marks" => 'Marks are required unless the student is marked absent.']);
            }

            \App\Models\ExamResult::updateOrCreate(
                ['exam_id' => $exam->id, 'student_id' => $studentId],
                [
                    'marks_obtained' => $isAbsent ? null : $marks,
                    'is_absent'      => $isAbsent,
                    'grade'          => $isAbsent ? null : ($result['grade'] ?? null),
                    'remarks'        => $result['remarks'] ?? null,
                ]
            );
        }
        return redirect()->route('admin.exams.show', $exam)->with('success', 'Results saved.');
    }

    private function authorizeExamManagement(): void
    {
        abort_unless(auth()->user() && AccessControl::canManageExams(auth()->user()), 403);
    }

    private function eligibleStudentsForExam(Exam $exam)
    {
        $termIds = $this->termIdsForExam($exam);

        return Student::where('program_id', $exam->program_id)
            ->where(function ($query) use ($exam, $termIds) {
                $query->whereHas('enrollments', function ($q) use ($exam) {
                    $q->where('subject_id', $exam->subject_id)
                        ->where('semester_id', $exam->semester_id)
                        ->whereIn('status', ['active', 'enrolled']);
                })->orWhereHas('subjectEnrollments', function ($q) use ($exam, $termIds) {
                    $q->where('subject_id', $exam->subject_id)
                        ->where('status', 'active');

                    $termIds === []
                        ? $q->whereRaw('1 = 0')
                        : $q->whereIn('term_id', $termIds);
                });
            });
    }

    private function termIdsForExam(Exam $exam): array
    {
        if ($exam->term_id) {
            return [(int) $exam->term_id];
        }

        if (! $exam->semester_id) {
            return [];
        }

        $semester = $exam->semester ?: Semester::find($exam->semester_id);
        if (! $semester) {
            return [];
        }

        return Term::query()
            ->when($exam->program_id, fn($q) => $q->where('program_id', $exam->program_id))
            ->where(fn($q) => $q->where('term_number', $semester->number)->orWhere('name', $semester->name))
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    private function hasExamOperationalHistory(Exam $exam): bool
    {
        return $exam->results()->exists()
            || ExamRegistration::where('exam_id', $exam->id)->exists();
    }

    private function examDateOutsideSemester(array $data): bool
    {
        $semester = Semester::find($data['semester_id'] ?? null);
        if (! $semester || ! $semester->start_date || ! $semester->end_date || empty($data['exam_date'])) {
            return false;
        }

        $examDate = \Illuminate\Support\Carbon::parse($data['exam_date'])->startOfDay();

        return $examDate->lt($semester->start_date->copy()->startOfDay())
            || $examDate->gt($semester->end_date->copy()->startOfDay());
    }

    private function syncExamProgramFromSubject(array $data, ?Exam $exam = null): array
    {
        $subject = Subject::find($data['subject_id'] ?? null);
        if ($subject?->program_id) {
            $data['program_id'] = $subject->program_id;
        } elseif ($exam?->program_id) {
            $data['program_id'] = $exam->program_id;
        }

        return $data;
    }

    private function changesExamContract(Exam $exam, array $data): bool
    {
        foreach (['program_id', 'semester_id', 'subject_id', 'type', 'total_marks', 'passing_marks'] as $field) {
            if ((string) ($exam->{$field} ?? '') !== (string) ($data[$field] ?? '')) {
                return true;
            }
        }

        return false;
    }
}
