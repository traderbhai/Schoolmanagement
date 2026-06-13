<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{Exam, ExamResult, Program, Student, Enrollment, Subject, Term, Batch, Classroom};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $classrooms = Classroom::orderBy('name')->get();
        return view('departmental.exam-cell.create-exam', compact('programs', 'subjects', 'terms', 'classrooms'));
    }

    public function storeExam(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'type'         => 'required|string|max:100',
            'program_id'   => 'required|exists:programs,id',
            'subject_id'   => 'required|exists:subjects,id',
            'term_id'      => 'nullable|exists:terms,id',
            'exam_date'    => 'required|date',
            'start_time'   => 'nullable|date_format:H:i',
            'end_time'     => 'nullable|date_format:H:i',
            'total_marks'  => 'required|numeric|min:1',
            'passing_marks'=> 'nullable|numeric|min:0',
            'classroom_id' => 'nullable|exists:classrooms,id',
        ]);

        Exam::create($data);
        return redirect()->route('exam-cell.exams')->with('success', 'Exam scheduled successfully.');
    }

    public function editExam(Exam $exam)
    {
        $programs   = Program::where('is_active', true)->orderBy('name')->get();
        $subjects   = Subject::orderBy('name')->get();
        $terms      = Term::orderByDesc('start_date')->get();
        $classrooms = Classroom::orderBy('name')->get();
        return view('departmental.exam-cell.edit-exam', compact('exam', 'programs', 'subjects', 'terms', 'classrooms'));
    }

    public function updateExam(Request $request, Exam $exam)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'type'         => 'required|string|max:100',
            'program_id'   => 'required|exists:programs,id',
            'subject_id'   => 'required|exists:subjects,id',
            'term_id'      => 'nullable|exists:terms,id',
            'exam_date'    => 'required|date',
            'start_time'   => 'nullable|date_format:H:i',
            'end_time'     => 'nullable|date_format:H:i',
            'total_marks'  => 'required|numeric|min:1',
            'passing_marks'=> 'nullable|numeric|min:0',
            'classroom_id' => 'nullable|exists:classrooms,id',
        ]);

        $exam->update($data);
        return redirect()->route('exam-cell.exams')->with('success', 'Exam updated.');
    }

    public function destroyExam(Exam $exam)
    {
        $exam->delete();
        return back()->with('success', 'Exam deleted.');
    }

    public function results(Request $request)
    {
        $exams = Exam::with(['program', 'subject'])->orderByDesc('exam_date')->get()
            ->map(function ($exam) {
                $enrolled = Student::where('program_id', $exam->program_id)->where('status', 'active')->count();
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

        $students = Student::where('program_id', $exam->program_id)->where('status', 'active')
            ->with(['user'])->get()
            ->map(function ($student) use ($exam) {
                $student->result = ExamResult::where('exam_id', $exam->id)->where('student_id', $student->id)->first();
                return $student;
            });

        return view('departmental.exam-cell.grade-sheet', compact('exam', 'students'));
    }

    public function saveMarks(Request $request, Exam $exam)
    {
        $request->validate([
            'marks'   => 'required|array',
            'marks.*' => 'nullable|numeric|min:0',
        ]);

        foreach ($request->marks as $studentId => $marks) {
            if ($marks === null || $marks === '') continue;
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
                    ['marks_obtained' => 0, 'is_absent' => true]
                );
            }
        }

        return back()->with('success', 'Marks saved successfully.');
    }

    public function publishResults(Request $request, Exam $exam)
    {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('exams');
        if (in_array('published_at', $columns)) {
            $exam->update(['published_at' => now()]);
        }
        ExamResult::where('exam_id', $exam->id)->update(['remarks' => 'Published']);
        return redirect()->route('exam-cell.grade-sheet', $exam)->with('success', 'Results published successfully.');
    }

    public function hallTickets(Request $request)
    {
        $programs = Program::where('is_active', true)->orderBy('name')->get();
        $exams    = Exam::with(['subject', 'program'])->where('exam_date', '>', now())->orderBy('exam_date')->get();
        $students = collect();
        $selectedExam = null;

        if ($request->filled('exam_id')) {
            $selectedExam = Exam::with(['subject', 'program', 'classroom'])->findOrFail($request->exam_id);
            $students = Student::where('program_id', $selectedExam->program_id)
                ->where('status', 'active')->with('user')->get();
        }

        return view('departmental.exam-cell.hall-tickets', compact('programs', 'exams', 'students', 'selectedExam'));
    }

    public function downloadHallTicket(Exam $exam, Student $student)
    {
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
            $appeals = \App\Models\MarksAppeal::with(['student.user', 'exam.subject'])
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

        try {
            $appeal = \App\Models\MarksAppeal::findOrFail($appealId);
            $appeal->update([
                'status'        => $request->action,
                'reviewer_id'   => auth()->id(),
                'remarks'       => $request->remarks,
                'revised_marks' => $request->revised_marks,
                'reviewed_at'   => now(),
            ]);
            if ($request->action === 'approved' && $request->revised_marks !== null) {
                ExamResult::where('exam_id', $appeal->exam_id)
                    ->where('student_id', $appeal->student_id)
                    ->update(['marks_obtained' => $request->revised_marks]);
            }
        } catch (\Exception $e) {}

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
}
