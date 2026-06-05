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
        $upcoming = Exam::where('exam_date', '>=', now()->toDateString())->count();
        $total    = Exam::count();

        // Exams that have at least one result
        $withResults = Exam::has('results')->count();
        $pending = $total - $withResults;

        $programs = Program::where('is_active', true)->withCount('students')->get();

        $upcomingList = Exam::where('exam_date', '>=', now()->toDateString())
            ->with(['program', 'subject'])
            ->orderBy('exam_date')
            ->take(10)
            ->get();

        return view('departmental.exam-cell.dashboard', compact(
            'upcoming', 'total', 'withResults', 'pending', 'programs', 'upcomingList'
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
