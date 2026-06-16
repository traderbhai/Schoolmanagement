<?php
namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{Assignment, AssignmentSubmission, Subject, Term, TimetableEntry, Student};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssignmentController extends Controller
{
    private function teacherSubjectIds(): array
    {
        $teacher = auth()->user()->teacher;
        if (!$teacher) return [];
        return TimetableEntry::where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->pluck('subject_id')->unique()->toArray();
    }

    private function ensureTeachesSubject(int $subjectId): void
    {
        abort_unless(in_array($subjectId, $this->teacherSubjectIds(), true), 403, 'You do not teach this subject.');
    }

    private function assignmentRosterQuery(Assignment $assignment)
    {
        return Student::where(function ($query) use ($assignment) {
            $query->whereHas('subjectEnrollments', function ($enrollment) use ($assignment) {
                $enrollment->where('subject_id', $assignment->subject_id)
                    ->where('status', 'active')
                    ->when($assignment->term_id, fn ($termQuery) => $termQuery->where(function ($nested) use ($assignment) {
                        $nested->whereNull('term_id')->orWhere('term_id', $assignment->term_id);
                    }));
            })->orWhereHas('enrollments', function ($enrollment) use ($assignment) {
                $enrollment->where('subject_id', $assignment->subject_id)
                    ->whereIn('status', ['active', 'enrolled'])
                    ->when($assignment->term_id, fn ($termQuery) => $termQuery->where(function ($nested) use ($assignment) {
                        $nested->whereNull('term_id')->orWhere('term_id', $assignment->term_id);
                    }));
            });
        });
    }

    public function index()
    {
        $assignments = Assignment::whereIn('subject_id', $this->teacherSubjectIds())
            ->where('created_by', auth()->id())
            ->with('subject')
            ->withCount('submissions')
            ->latest()
            ->paginate(15);

        $subjects = Subject::whereIn('id', $this->teacherSubjectIds())->get();
        return view('teacher.assignments.index', compact('assignments', 'subjects'));
    }

    public function create()
    {
        $subjects    = Subject::whereIn('id', $this->teacherSubjectIds())->get();
        $currentTerm = Term::latest('start_date')->first();
        return view('teacher.assignments.create', compact('subjects', 'currentTerm'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject_id'           => 'required|exists:subjects,id',
            'title'                => 'required|string|max:255',
            'description'          => 'nullable|string',
            'instructions'         => 'nullable|string',
            'max_marks'            => 'required|integer|min:1',
            'due_at'               => 'required|date',
            'allow_late_submission'=> 'boolean',
            'late_penalty_percent' => 'nullable|integer|min:0|max:100',
            'attachment'           => 'nullable|file|max:10240',
        ]);
        $this->ensureTeachesSubject((int) $request->subject_id);

        $path = $request->hasFile('attachment')
            ? $request->file('attachment')->store('assignments', 'public') : null;

        Assignment::create([
            'subject_id'            => $request->subject_id,
            'created_by'            => auth()->id(),
            'term_id'               => Term::latest('start_date')->first()?->id,
            'title'                 => $request->title,
            'description'           => $request->description,
            'instructions'          => $request->instructions,
            'attachment_path'       => $path,
            'max_marks'             => $request->max_marks,
            'due_at'                => $request->due_at,
            'allow_late_submission' => $request->boolean('allow_late_submission'),
            'late_penalty_percent'  => $request->late_penalty_percent ?? 0,
            'is_published'          => $request->boolean('is_published', true),
        ]);

        return redirect()->route('teacher.assignments.index')->with('success', 'Assignment created.');
    }

    public function submissions(Assignment $assignment)
    {
        if ($assignment->created_by !== auth()->id()) abort(403);

        $submissions = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->with(['student.user'])
            ->latest('submitted_at')
            ->get();

        $enrolledStudents = $this->assignmentRosterQuery($assignment)->with('user')->get();

        $submittedIds = $submissions->pluck('student_id')->toArray();
        $notSubmitted = $enrolledStudents->whereNotIn('id', $submittedIds);

        return view('teacher.assignments.submissions', compact('assignment', 'submissions', 'notSubmitted'));
    }

    public function grade(Request $request, AssignmentSubmission $submission)
    {
        abort_unless($submission->assignment && $submission->assignment->created_by === auth()->id(), 403);
        abort_unless($this->assignmentRosterQuery($submission->assignment)->whereKey($submission->student_id)->exists(), 403);

        $request->validate([
            'marks_obtained' => 'required|numeric|min:0|max:'.$submission->assignment->max_marks,
            'feedback'       => 'nullable|string|max:500',
        ]);

        $submission->update([
            'marks_obtained' => $request->marks_obtained,
            'feedback'       => $request->feedback,
            'graded_by'      => auth()->id(),
            'graded_at'      => now(),
            'status'         => 'graded',
        ]);

        return back()->with('success', 'Graded.');
    }
}
