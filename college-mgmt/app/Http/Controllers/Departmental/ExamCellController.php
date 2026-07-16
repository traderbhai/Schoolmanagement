<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{Exam, ExamAnomalyLog, ExamRegistration, ExamResult, MarksAppeal, Notification, Program, Student, Subject, Term, Classroom, Semester};
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class ExamCellController extends Controller
{
    public function dashboard()
    {
        $total    = Exam::count();
        $upcoming = Exam::where('exam_date', '>', now())->count();
        $past     = Exam::where('exam_date', '<=', now())->count();

        $examsNeedingResults = Exam::where('exam_date', '<=', now())->withCount('results')->get()
            ->filter(fn($e) => $e->results_count === 0);
        $pending = $examsNeedingResults->count();

        $withResults = $past - $pending;
        $completionPct = $past > 0 ? round(($withResults / $past) * 100, 1) : 100;

        $published = 0;
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('exams', 'is_results_published')) {
                $published = Exam::where('is_results_published', true)->count();
            }
        } catch (\Exception $e) {}

        $recentExams = Exam::with(['subject', 'program'])->latest('exam_date')->take(10)->get()
            ->map(function ($exam) {
                $results = ExamResult::where('exam_id', $exam->id)->get();
                $exam->result_count = $results->count();
                if ($results->count() > 0) {
                    $exam->avg_marks  = round($results->avg('marks_obtained'), 1);
                    $exam->pass_count = $results->where('marks_obtained', '>=', ($exam->passing_marks ?? 40))->count();
                    $exam->pass_pct   = round(($exam->pass_count / $results->count()) * 100, 1);
                } else {
                    $exam->avg_marks = $exam->pass_count = $exam->pass_pct = null;
                }
                return $exam;
            });

        $upcomingExams = Exam::with(['subject', 'program'])->where('exam_date', '>', now())
            ->orderBy('exam_date')->take(5)->get();

        try { $anomalyCount = \App\Models\ExamAnomalyLog::whereNull('resolved_at')->count(); }
        catch (\Exception $e) { $anomalyCount = 0; }

        try { $pendingAppeals = \App\Models\MarksAppeal::whereIn('status', ['pending', 'under_review'])->count(); }
        catch (\Exception $e) { $pendingAppeals = 0; }

        $nextExam = $upcomingExams->first();
        $priority = $this->examCellPriority($anomalyCount, $pendingAppeals, $pending, $nextExam, $total);

        return view('departmental.exam-cell.dashboard', compact(
            'total', 'upcoming', 'pending', 'withResults', 'completionPct', 'published',
            'recentExams', 'upcomingExams', 'anomalyCount', 'pendingAppeals', 'priority'
        ));
    }

    public function exams(Request $request)
    {
        $query = Exam::with(['program', 'subject', 'term'])->withCount('results');

        if ($request->filled('program_id')) $query->where('program_id', $request->program_id);
        if ($request->filled('term_id'))    $query->where('term_id', $request->term_id);
        if ($request->filled('status')) {
            if ($request->status === 'upcoming') $query->where('exam_date', '>=', now());
            elseif ($request->status === 'past')  $query->where('exam_date', '<', now());
        }

        $exams    = $query->orderByDesc('exam_date')->paginate(25)->withQueryString();
        $programs = Program::where('is_active', true)->orderBy('name')->get();
        $terms    = Term::orderByDesc('start_date')->get();

        return view('departmental.exam-cell.exams', compact('exams', 'programs', 'terms'));
    }

    public function createExam()
    {
        $programs   = Program::where('is_active', true)->orderBy('name')->get();
        $subjects   = Subject::orderBy('name')->get();
        $terms      = Term::orderByDesc('start_date')->get();
        $semesters  = Semester::orderByDesc('start_date')->get();
        $classrooms = Classroom::orderBy('name')->get();
        return view('departmental.exam-cell.create-exam', compact('programs', 'subjects', 'terms', 'semesters', 'classrooms'));
    }

    public function storeExam(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'type'         => 'required|string|max:100',
            'program_id'   => 'required|exists:programs,id',
            'semester_id'  => 'nullable|exists:semesters,id',
            'subject_id'   => 'required|exists:subjects,id',
            'term_id'      => 'nullable|exists:terms,id',
            'exam_date'    => 'required|date',
            'start_time'   => 'nullable|date_format:H:i',
            'end_time'     => 'nullable|date_format:H:i',
            'total_marks'  => 'required|numeric|min:1',
            'passing_marks'=> 'nullable|numeric|min:0',
            'classroom_id' => 'nullable|exists:classrooms,id',
        ]);
        $semesterWasSubmitted = $request->filled('semester_id');
        $data = $this->normalizeExamSemester($data);

        if ($errors = $this->examAcademicContractErrors($data, $semesterWasSubmitted)) {
            return back()->withInput()->withErrors($errors);
        }

        Exam::create($data);
        return redirect()->route('exam-cell.exams')->with('success', 'Exam scheduled successfully.');
    }

    public function editExam(Exam $exam)
    {
        $programs   = Program::where('is_active', true)->orderBy('name')->get();
        $subjects   = Subject::orderBy('name')->get();
        $terms      = Term::orderByDesc('start_date')->get();
        $semesters  = Semester::orderByDesc('start_date')->get();
        $classrooms = Classroom::orderBy('name')->get();
        return view('departmental.exam-cell.edit-exam', compact('exam', 'programs', 'subjects', 'terms', 'semesters', 'classrooms'));
    }

    public function updateExam(Request $request, Exam $exam)
    {
        if ($exam->published_at) {
            return redirect()->route('exam-cell.exams')
                ->with('error', 'Published exams cannot be edited because official result history is locked.');
        }

        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'type'         => 'required|string|max:100',
            'program_id'   => 'required|exists:programs,id',
            'semester_id'  => 'nullable|exists:semesters,id',
            'subject_id'   => 'required|exists:subjects,id',
            'term_id'      => 'nullable|exists:terms,id',
            'exam_date'    => 'required|date',
            'start_time'   => 'nullable|date_format:H:i',
            'end_time'     => 'nullable|date_format:H:i',
            'total_marks'  => 'required|numeric|min:1',
            'passing_marks'=> 'nullable|numeric|min:0',
            'classroom_id' => 'nullable|exists:classrooms,id',
        ]);
        $semesterWasSubmitted = $request->filled('semester_id');
        $data = $this->normalizeExamSemester($data, $exam);

        if ($errors = $this->examAcademicContractErrors($data, $semesterWasSubmitted)) {
            return back()->withInput()->withErrors($errors);
        }

        if ($this->hasExamOperationalHistory($exam) && $this->changesExamContract($exam, $data)) {
            return redirect()->route('exam-cell.exams')
                ->with('error', 'Exams with result or registration history cannot have program, subject, term, type, or marks-scale fields changed.');
        }

        $exam->update($data);
        return redirect()->route('exam-cell.exams')->with('success', 'Exam updated.');
    }

    public function destroyExam(Exam $exam)
    {
        if ($exam->published_at) {
            return back()->with('error', 'Published exams cannot be deleted because official result history is locked.');
        }

        if ($this->hasExamOperationalHistory($exam)) {
            return back()->with('error', 'Exams with result or registration history cannot be deleted. Archive or cancel through an audited exam workflow instead.');
        }

        $exam->delete();
        return back()->with('success', 'Exam deleted.');
    }

    public function results(Request $request)
    {
        $exams = Exam::with(['program', 'subject'])->orderByDesc('exam_date')->get()
            ->map(function ($exam) {
                $enrolled = $this->eligibleStudentQuery($exam)->count();
                $entered  = $exam->results()->count();
                $exam->enrolled       = $enrolled;
                $exam->entered        = $entered;
                $exam->completion_pct = $enrolled > 0 ? round(($entered / $enrolled) * 100) : 0;
                return $exam;
            });

        return view('departmental.exam-cell.results', compact('exams'));
    }

    public function gradeSheet(Exam $exam)
    {
        $exam->load(['program', 'subject', 'term']);

        $students = $this->eligibleStudentQuery($exam)
            ->with(['user'])->get()
            ->map(function ($student) use ($exam) {
                $student->result = ExamResult::where('exam_id', $exam->id)->where('student_id', $student->id)->first();
                return $student;
            });

        return view('departmental.exam-cell.grade-sheet', compact('exam', 'students'));
    }

    public function saveMarks(Request $request, Exam $exam)
    {
        if ($exam->published_at) {
            return back()->with('error', 'Published results are locked. Reopen through an approved correction workflow before editing marks.');
        }

        if ($exam->exam_date && $exam->exam_date->isFuture()) {
            return back()->with('error', 'Exam results cannot be entered before the exam date.');
        }

        $request->validate([
            'marks'   => 'required|array',
            'marks.*' => 'nullable|numeric|min:0|max:' . (float) $exam->total_marks,
            'absent' => 'nullable|array',
            'absent.*' => 'integer',
        ]);

        $eligibleStudentIds = $this->eligibleStudentQuery($exam)->pluck('id')->map(fn($id) => (string) $id)->all();
        $absentStudentIds = collect($request->input('absent', []))
            ->map(fn($id) => (string) $id)
            ->unique()
            ->values();
        $submittedStudentIds = collect(array_keys($request->marks ?? []))
            ->merge($request->input('absent', []))
            ->map(fn($id) => (string) $id)
            ->unique()
            ->values();
        if ($submittedStudentIds->diff($eligibleStudentIds)->isNotEmpty()) {
            return back()->withErrors(['marks' => 'Marks can be saved only for students enrolled in this exam subject and term.']);
        }

        foreach ($request->marks as $studentId => $marks) {
            $studentKey = (string) $studentId;
            if ($absentStudentIds->contains($studentKey)) {
                continue;
            }

            if ($marks === null || $marks === '') {
                return back()
                    ->withInput()
                    ->withErrors(["marks.{$studentId}" => 'Marks are required unless the student is marked absent.']);
            }

            ExamResult::updateOrCreate(
                ['exam_id' => $exam->id, 'student_id' => $studentId],
                ['marks_obtained' => $marks, 'is_absent' => false]
            );
        }

        // Mark absents
        if ($request->filled('absent')) {
            foreach ($request->absent as $studentId) {
                ExamResult::updateOrCreate(
                    ['exam_id' => $exam->id, 'student_id' => $studentId],
                    ['marks_obtained' => null, 'is_absent' => true]
                );
            }
        }

        return back()->with('success', 'Marks saved successfully.');
    }

    public function publishResults(Request $request, Exam $exam)
    {
        if ($exam->published_at) {
            return back()->with('error', 'Results are already published for this exam.');
        }

        if ($exam->exam_date && $exam->exam_date->isFuture()) {
            return back()->with('error', 'Results cannot be published before the exam date.');
        }

        $eligibleStudentIds = $this->eligibleStudentQuery($exam)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values();

        if ($eligibleStudentIds->isEmpty()) {
            return back()->with('error', 'Results cannot be published because no eligible students were found for this exam.');
        }

        $enteredStudentIds = ExamResult::where('exam_id', $exam->id)
            ->whereIn('student_id', $eligibleStudentIds)
            ->pluck('student_id')
            ->map(fn($id) => (int) $id)
            ->values();

        $missingCount = $eligibleStudentIds->diff($enteredStudentIds)->count();
        if ($missingCount > 0) {
            return back()->with('error', "Results cannot be published until marks or absence are entered for {$missingCount} eligible student(s).");
        }

        $openAnomalyCount = ExamAnomalyLog::where('exam_id', $exam->id)
            ->whereNull('resolved_at')
            ->count();
        if ($openAnomalyCount > 0) {
            return back()->with('error', "Results cannot be published while {$openAnomalyCount} exam anomaly case(s) are unresolved.");
        }

        $openAppealCount = MarksAppeal::whereHas('examResult', fn($query) => $query->where('exam_id', $exam->id))
            ->whereIn('status', ['pending', 'under_review'])
            ->count();
        if ($openAppealCount > 0) {
            return back()->with('error', "Results cannot be published while {$openAppealCount} marks appeal(s) are pending review.");
        }

        $exam->forceFill([
            'published_at' => now(),
            'published_by' => auth()->id(),
        ])->save();

        $this->queueResultPublishedNotifications($exam);

        return redirect()->route('exam-cell.grade-sheet', $exam)->with('success', 'Results published successfully.');
    }

    private function queueResultPublishedNotifications(Exam $exam): void
    {
        $actionUrl = route('student.results', array_filter(['semester_id' => $exam->semester_id]));
        $title = 'Exam result published';
        $subjectName = $exam->subject?->name ?? 'your subject';
        $examName = $exam->name ?? 'exam';

        $this->eligibleStudentQuery($exam)
            ->whereNotNull('user_id')
            ->get(['id', 'user_id'])
            ->each(function (Student $student) use ($actionUrl, $title, $subjectName, $examName) {
                Notification::updateOrCreate(
                    [
                        'user_id' => $student->user_id,
                        'type' => 'result_published',
                        'action_url' => $actionUrl,
                        'title' => $title,
                    ],
                    [
                        'message' => "Your result for {$examName} ({$subjectName}) has been published.",
                        'is_read' => false,
                        'read_at' => null,
                    ]
                );
            });
    }

    public function hallTickets(Request $request)
    {
        $programs = Program::where('is_active', true)->orderBy('name')->get();
        $exams    = Exam::with(['subject', 'program'])->where('exam_date', '>', now())->orderBy('exam_date')->get();
        $students = collect();
        $registrations = collect();
        $selectedExam = null;

        if ($request->filled('exam_id')) {
            $selectedExam = Exam::with(['subject', 'program', 'classroom'])->findOrFail($request->exam_id);
            $students = $this->hallTicketEligibleStudentQuery($selectedExam)->with('user')->get();
            $registrations = ExamRegistration::with(['student.user', 'student.program', 'approver'])
                ->where('exam_id', $selectedExam->id)
                ->orderByRaw("case status when 'pending' then 0 when 'approved' then 1 else 2 end")
                ->latest()
                ->get();
        }

        return view('departmental.exam-cell.hall-tickets', compact('programs', 'exams', 'students', 'registrations', 'selectedExam'));
    }

    public function reviewRegistration(Request $request, ExamRegistration $registration)
    {
        $data = $request->validate([
            'action' => 'required|in:approved,rejected',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $registration->load('exam');
        if ($registration->exam?->published_at) {
            return back()->with('error', 'Registrations for a published exam cannot be reviewed.');
        }

        if ($data['action'] === 'approved' && (! $registration->attendance_eligible || ! $registration->fee_cleared)) {
            return back()->with('error', 'Only fee-cleared and attendance-eligible registrations can be approved for hall tickets.');
        }

        $registration->update([
            'status' => $data['action'],
            'remarks' => $data['remarks'] ?? null,
            'approved_by' => auth()->id(),
        ]);

        return back()->with('success', 'Exam registration ' . $data['action'] . '.');
    }

    public function downloadHallTicket(Exam $exam, Student $student)
    {
        abort_unless($this->hallTicketEligibleStudentQuery($exam)->whereKey($student->id)->exists(), 403);

        $exam->load(['subject', 'program', 'classroom', 'term']);
        $student->load('user');

        $pdf = Pdf::loadView('departmental.exam-cell.hall-ticket-pdf', compact('exam', 'student'));
        $pdf->setPaper('A5', 'portrait');
        return $pdf->download("hall-ticket-{$student->enrollment_number}-{$exam->id}.pdf");
    }

    public function marksAppeals(Request $request)
    {
        $appeals = collect();
        try {
            $appeals = \App\Models\MarksAppeal::with(['student.user', 'examResult.exam.subject'])
                ->latest()->paginate(25)->withQueryString();
        } catch (\Exception $e) {}

        return view('departmental.exam-cell.marks-appeals', compact('appeals'));
    }

    public function reviewAppeal(Request $request, $appealId)
    {
        $request->validate([
            'action'   => 'required|in:approved,rejected,under_review',
            'remarks'  => 'nullable|string|max:1000',
            'revised_marks' => 'nullable|numeric|min:0',
        ]);

        $appeal = \App\Models\MarksAppeal::with('examResult.exam')->findOrFail($appealId);
        if (in_array($appeal->status, ['resolved', 'rejected'], true)) {
            return back()->with('error', 'Reviewed marks appeal history cannot be changed.');
        }

        $result = $appeal->examResult;
        $exam = $result?->exam;
        if (! $result || ! $exam || ! $exam->published_at || $result->is_absent || (int) $result->student_id !== (int) $appeal->student_id) {
            return back()->with('error', 'This marks appeal is no longer valid for correction because the official published result is unavailable or not appealable.');
        }

        $action = $request->action;
        $remarks = trim((string) $request->remarks);

        if (in_array($action, ['approved', 'rejected'], true) && $remarks === '') {
            return back()->withErrors(['remarks' => 'Review remarks are required before a final appeal decision.']);
        }

        if ($action === 'approved') {
            if ($request->revised_marks === null || $request->revised_marks === '') {
                return back()->withErrors(['revised_marks' => 'Revised marks are required when approving an appeal.']);
            }

            $totalMarks = $exam->total_marks;
            if ($totalMarks !== null && (float) $request->revised_marks > (float) $totalMarks) {
                return back()->withErrors(['revised_marks' => 'Revised marks cannot exceed the exam total marks.']);
            }
        }

        $status = $request->action === 'approved' ? 'resolved' : $request->action;

        $appeal->update([
            'status'         => $status,
            'reviewed_by'    => auth()->id(),
            'review_remarks' => $remarks !== '' ? $remarks : null,
            'revised_marks'  => $action === 'approved' ? $request->revised_marks : null,
            'reviewed_at'    => now(),
        ]);

        if ($request->action === 'approved' && $request->revised_marks !== null) {
            $result->update(['marks_obtained' => $request->revised_marks]);
        }

        return back()->with('success', 'Appeal reviewed.');
    }

    private function examCellPriority(int $anomalyCount, int $pendingAppeals, int $pendingResults, ?Exam $nextExam, int $totalExams): array
    {
        if ($anomalyCount > 0) {
            return [
                'level' => 'danger',
                'title' => "Resolve {$anomalyCount} open exam anomal" . ($anomalyCount === 1 ? 'y' : 'ies'),
                'body' => 'Review malpractice, attendance, paper, or process anomalies before publishing dependent results.',
                'route' => route('exam-cell.anomalies.index'),
                'action' => 'Review Anomalies',
            ];
        }

        if ($pendingAppeals > 0) {
            return [
                'level' => 'warning',
                'title' => "Review {$pendingAppeals} pending marks appeal" . ($pendingAppeals === 1 ? '' : 's'),
                'body' => 'Close student marks appeals with clear remarks before final result sign-off.',
                'route' => route('exam-cell.marks-appeals'),
                'action' => 'Open Appeals',
            ];
        }

        if ($pendingResults > 0) {
            return [
                'level' => 'warning',
                'title' => "Enter results for {$pendingResults} completed exam" . ($pendingResults === 1 ? '' : 's'),
                'body' => 'Completed exams without marks block publication, transcripts, and parent/student visibility.',
                'route' => route('exam-cell.results'),
                'action' => 'Enter Results',
            ];
        }

        if ($nextExam) {
            return [
                'level' => 'info',
                'title' => 'Prepare hall tickets for the next exam',
                'body' => $nextExam->name . ' is scheduled on ' . $nextExam->exam_date->format('d M Y') . '. Confirm room, subject, and student eligibility.',
                'route' => route('exam-cell.hall-tickets', ['exam_id' => $nextExam->id]),
                'action' => 'Prepare Hall Tickets',
            ];
        }

        if ($totalExams === 0) {
            return [
                'level' => 'warning',
                'title' => 'Schedule the first exam',
                'body' => 'No exams are scheduled yet. Create an exam to start hall ticket, marks entry, and publication workflows.',
                'route' => route('exam-cell.exams.create'),
                'action' => 'Schedule Exam',
            ];
        }

        return [
            'level' => 'none',
            'title' => 'No urgent exam-cell action today',
            'body' => 'Use this time to audit schedules, verify hall tickets, review reports, or prepare the next exam cycle.',
            'route' => route('exam-cell.exams'),
            'action' => 'View Exams',
        ];
    }

    private function eligibleStudentQuery(Exam $exam)
    {
        $query = Student::query()
            ->where('status', 'active')
            ->where('program_id', $exam->program_id);

        if ($exam->subject_id) {
            $query->where(function ($studentQuery) use ($exam) {
                $studentQuery->whereHas('subjectEnrollments', function ($enrollmentQuery) use ($exam) {
                        $enrollmentQuery->where('subject_id', $exam->subject_id)
                            ->where('status', 'active')
                            ->when($exam->term_id, fn($q) => $q->where('term_id', $exam->term_id));
                    })
                    ->orWhereHas('enrollments', function ($enrollmentQuery) use ($exam) {
                        $enrollmentQuery->where('subject_id', $exam->subject_id)
                            ->whereIn('status', ['enrolled', 'active'])
                            ->when($exam->term_id, fn($q) => $q->where('term_id', $exam->term_id));
                    });
            });
        }

        return $query->orderBy('enrollment_number');
    }

    private function hallTicketEligibleStudentQuery(Exam $exam)
    {
        if ($exam->published_at) {
            return Student::query()->whereRaw('1 = 0');
        }

        return $this->eligibleStudentQuery($exam)
            ->whereHas('examRegistrations', function ($query) use ($exam) {
                $query->where('exam_id', $exam->id)
                    ->where('status', 'approved')
                    ->where('attendance_eligible', true)
                    ->where('fee_cleared', true);
            });
    }

    private function hasExamOperationalHistory(Exam $exam): bool
    {
        return $exam->results()->exists()
            || ExamRegistration::where('exam_id', $exam->id)->exists();
    }

    private function changesExamContract(Exam $exam, array $data): bool
    {
        foreach (['program_id', 'semester_id', 'subject_id', 'term_id', 'type', 'total_marks', 'passing_marks'] as $field) {
            if ((string) ($exam->{$field} ?? '') !== (string) ($data[$field] ?? '')) {
                return true;
            }
        }

        return false;
    }

    private function examAcademicContractErrors(array $data, bool $semesterWasSubmitted = false): array
    {
        $errors = [];
        $program = Program::find($data['program_id'] ?? null);
        if (! $program || ! $program->is_active) {
            $errors['program_id'] = 'Select an active program for this exam.';
        }

        $subject = Subject::find($data['subject_id'] ?? null);
        if (! $subject || ! $subject->is_active) {
            $errors['subject_id'] = 'Select an active subject for this exam.';
        } elseif ($program && $subject->program_id !== null && (int) $subject->program_id !== (int) $program->id) {
            $errors['subject_id'] = 'Selected subject does not belong to the selected program.';
        }

        if (! empty($data['term_id'])) {
            $term = Term::find($data['term_id']);
            if (! $term || (int) $term->program_id !== (int) ($data['program_id'] ?? 0)) {
                $errors['term_id'] = 'Selected term does not belong to the selected program.';
            }
        }

        $semester = Semester::find($data['semester_id'] ?? null);
        if (! $semester) {
            $errors['semester_id'] = 'Select a valid semester for this exam.';
        } elseif ($semesterWasSubmitted && ! empty($data['term_id'])) {
            $term = $term ?? Term::find($data['term_id']);
            if ($term && (int) $semester->number !== (int) $term->term_number) {
                $errors['semester_id'] = 'Selected semester does not match the selected term.';
            }
        }

        if (! empty($data['start_time']) && ! empty($data['end_time']) && $data['end_time'] <= $data['start_time']) {
            $errors['end_time'] = 'Exam end time must be after the start time.';
        }

        if (isset($data['passing_marks'], $data['total_marks']) && (float) $data['passing_marks'] > (float) $data['total_marks']) {
            $errors['passing_marks'] = 'Passing marks cannot be greater than total marks.';
        }

        if (
            $semester
            && ! empty($data['exam_date'])
            && $semester->start_date
            && $semester->end_date
        ) {
            $examDate = \Illuminate\Support\Carbon::parse($data['exam_date'])->startOfDay();
            $startsOn = \Illuminate\Support\Carbon::parse($semester->start_date)->startOfDay();
            $endsOn = \Illuminate\Support\Carbon::parse($semester->end_date)->startOfDay();

            if ($examDate->lt($startsOn) || $examDate->gt($endsOn)) {
                $errors['exam_date'] = 'Exam date must fall within the selected semester window.';
            }
        }

        if (! empty($data['classroom_id'])) {
            $classroom = Classroom::find($data['classroom_id']);
            if (! $classroom || ! $classroom->is_active) {
                $errors['classroom_id'] = 'Select an active classroom for this exam.';
            }
        }

        return $errors;
    }

    private function normalizeExamSemester(array $data, ?Exam $exam = null): array
    {
        if (! empty($data['semester_id'])) {
            return $data;
        }

        if ($exam?->semester_id) {
            $data['semester_id'] = $exam->semester_id;

            return $data;
        }

        if (! empty($data['term_id'])) {
            $term = Term::find($data['term_id']);
            $semester = $term
                ? Semester::where('number', $term->term_number)->first()
                    ?: Semester::where('name', $term->name)->first()
                : null;
            if ($semester) {
                $data['semester_id'] = $semester->id;

                return $data;
            }
        }

        $data['semester_id'] = Semester::current()?->id ?? Semester::first()?->id;

        return $data;
    }
}
