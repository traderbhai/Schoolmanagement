<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AssignmentController extends Controller
{
    private function getStudent()
    {
        $student = Auth::user()->student;
        abort_unless($student, 403, 'Student record not found.');
        return $student;
    }

    public function index()
    {
        $student = $this->getStudent();

        $subjectIds = $student->subjects()->pluck('subjects.id');

        $assignments = Assignment::with(['subject', 'submissions' => fn($q) => $q->where('student_id', $student->id)])
            ->whereIn('subject_id', $subjectIds)
            ->where('is_published', true)
            ->orderBy('due_at')
            ->get();

        $now = now();
        $upcoming = $assignments->filter(fn($a) => $a->due_at->isFuture());
        $past = $assignments->filter(fn($a) => $a->due_at->isPast());

        return view('student.assignments.index', compact('upcoming', 'past', 'now'));
    }

    public function show(Assignment $assignment)
    {
        $student = $this->getStudent();
        abort_unless($assignment->is_published, 404);

        $submission = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();

        return view('student.assignments.show', compact('assignment', 'submission'));
    }

    public function submit(Request $request, Assignment $assignment)
    {
        $student = $this->getStudent();
        abort_unless($assignment->is_published, 403);

        if (! $assignment->allow_late_submission && $assignment->due_at->isPast()) {
            return back()->with('error', 'Submission deadline has passed.');
        }

        $request->validate([
            'answer_text' => 'nullable|string',
            'file'        => 'nullable|file|max:10240',
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store("assignments/{$assignment->id}", 'local');
        }

        $isLate = $assignment->due_at->isPast();

        AssignmentSubmission::updateOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $student->id],
            [
                'answer_text' => $request->answer_text,
                'file_path'   => $filePath,
                'submitted_at' => now(),
                'is_late'     => $isLate,
                'status'      => 'submitted',
            ]
        );

        return redirect()->route('student.assignments.show', $assignment)
            ->with('success', 'Assignment submitted successfully.');
    }
}
