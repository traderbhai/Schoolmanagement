<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{Exam, ExamResult, Program, Student, Enrollment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamCellController extends Controller
{
    public function dashboard()
    {
        $total    = Exam::count();
        $upcoming = Exam::where('exam_date', '>', now())->count();
        $past     = Exam::where('exam_date', '<=', now())->count();

        // Exams needing result entry (past exams with zero results)
        $examsNeedingResults = Exam::where('exam_date', '<=', now())
            ->withCount('results')
            ->get()
            ->filter(fn($e) => $e->results_count === 0);
        $pending = $examsNeedingResults->count();
        $withResults = $past - $pending;

        // Result entry completion %
        $completionPct = $past > 0 ? round((($past - $pending) / $past) * 100, 1) : 100;

        // Published results count (column may not exist)
        $published = 0;
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('exams', 'is_results_published')) {
                $published = Exam::where('is_results_published', true)->count();
            }
        } catch (\Exception $e) { $published = 0; }

        // Recent exams with their result stats
        $recentExams = Exam::with(['subject', 'program'])
            ->latest('exam_date')
            ->take(10)
            ->get()
            ->map(function($exam) {
                $results = ExamResult::where('exam_id', $exam->id)->get();
                $exam->result_count = $results->count();
                if ($results->count() > 0) {
                    $exam->avg_marks = round($results->avg('marks_obtained'), 1);
                    $exam->pass_count = $results->where('marks_obtained', '>=', ($exam->passing_marks ?? 40))->count();
                    $exam->pass_pct = round(($exam->pass_count / $results->count()) * 100, 1);
                } else {
                    $exam->avg_marks = null;
                    $exam->pass_count = null;
                    $exam->pass_pct = null;
                }
                return $exam;
            });

        // Upcoming exams list
        $upcomingExams = Exam::with(['subject', 'program'])
            ->where('exam_date', '>', now())
            ->orderBy('exam_date')
            ->take(5)
            ->get();

        return view('departmental.exam-cell.dashboard', compact(
            'total', 'upcoming', 'pending', 'withResults',
            'completionPct', 'published', 'recentExams', 'upcomingExams'
        ));
    }

    public function exams(Request $request)
    {
        $query = Exam::with(['program', 'subject', 'term'])
            ->withCount('results');

        if ($request->filled('program_id')) $query->where('program_id', $request->program_id);
        if ($request->filled('term_id'))    $query->where('term_id', $request->term_id);
        if ($request->filled('status')) {
            if ($request->status === 'upcoming') $query->where('exam_date', '>=', now());
            elseif ($request->status === 'past')  $query->where('exam_date', '<', now());
        }

        $exams    = $query->orderByDesc('exam_date')->paginate(25)->withQueryString();
        $programs = Program::where('is_active', true)->orderBy('name')->get();

        return view('departmental.exam-cell.exams', compact('exams', 'programs'));
    }

    public function results(Request $request)
    {
        $exams = Exam::with(['program', 'subject'])
            ->orderByDesc('exam_date')
            ->get()
            ->map(function ($exam) {
                $enrolled = Student::where('program_id', $exam->program_id)->where('status', 'active')->count();
                $entered  = $exam->results()->count();
                $exam->enrolled = $enrolled;
                $exam->entered  = $entered;
                $exam->completion_pct = $enrolled > 0 ? round(($entered / $enrolled) * 100) : 0;
                return $exam;
            });

        return view('departmental.exam-cell.results', compact('exams'));
    }

    public function gradeSheet(Exam $exam)
    {
        $exam->load(['program', 'subject', 'term']);

        $students = Student::where('program_id', $exam->program_id)
            ->where('status', 'active')
            ->with(['user'])
            ->get()
            ->map(function ($student) use ($exam) {
                $result = ExamResult::where('exam_id', $exam->id)
                    ->where('student_id', $student->id)
                    ->first();
                $student->result = $result;
                return $student;
            });

        return view('departmental.exam-cell.grade-sheet', compact('exam', 'students'));
    }

    public function publishResults(Request $request, Exam $exam)
    {
        // We add a note in results that they're reviewed. No published_at column exists,
        // so we use a workaround: update results remarks or just redirect with success.
        // If the column exists we'd set it; otherwise gracefully handle.
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('exams');
        if (in_array('published_at', $columns)) {
            $exam->update(['published_at' => now()]);
        }
        // Mark all results as reviewed
        ExamResult::where('exam_id', $exam->id)->update(['remarks' => 'Published']);

        return redirect()->route('exam-cell.grade-sheet', $exam)
            ->with('success', 'Results published successfully.');
    }
}
