<?php
namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{Assignment, AssignmentSubmission, Subject, Term, TimetableEntry, Student};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssignmentController extends Controller
{
    private function activeTeacher()
    {
        return auth()->user()->teacher;
    }

    private function ensureActiveTeacher(): void
    {
        abort_unless($this->activeTeacher()?->status === 'active', 403, 'Only active teachers can manage assignments.');
    }

    private function teacherSubjectIds(): array
    {
        $teacher = auth()->user()->teacher;
        if (!$teacher) return [];
        return TimetableEntry::where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function ($query) {
                $query->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn ($version) => $version->where('status', 'published'));
            })
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

    public function index(Request $request)
    {
        $subjectIds = $this->teacherSubjectIds();

        $assignments = Assignment::whereIn('subject_id', $subjectIds)
            ->where('created_by', auth()->id())
            ->with('subject')
            ->when($request->filled('subject_id'), function ($query) use ($request, $subjectIds) {
                $subjectId = (int) $request->subject_id;

                return in_array($subjectId, $subjectIds, true)
                    ? $query->where('subject_id', $subjectId)
                    : $query->whereRaw('1 = 0');
            })
            ->latest()
            ->paginate(15);

        $assignments->getCollection()->each(function (Assignment $assignment) {
            $assignment->setAttribute('submissions_count', $this->rosterSubmissionCount($assignment));
        });

        $subjects = Subject::whereIn('id', $subjectIds)->get();
        $canManageAssignments = $this->activeTeacher()?->status === 'active';
        return view('teacher.assignments.index', compact('assignments', 'subjects', 'canManageAssignments'));
    }

    public function create()
    {
        if ($this->activeTeacher()?->status !== 'active') {
            return redirect()
                ->route('teacher.assignments.index')
                ->with('error', 'Only active teachers can create assignments.');
        }

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
            'due_at'               => 'required|date|after:now',
            'allow_late_submission'=> 'boolean',
            'late_penalty_percent' => 'nullable|integer|min:0|max:100',
            'attachment'           => 'nullable|file|max:10240',
        ]);
        $this->ensureActiveTeacher();
        $this->ensureTeachesSubject((int) $request->subject_id);

        $title = trim((string) $request->input('title'));
        if ($title === '') {
            return back()
                ->withErrors(['title' => 'Enter a non-blank assignment title.'])
                ->withInput();
        }

        $path = $request->hasFile('attachment')
            ? $request->file('attachment')->store('assignments', 'public') : null;

        Assignment::create([
            'subject_id'            => $request->subject_id,
            'created_by'            => auth()->id(),
            'term_id'               => Term::latest('start_date')->first()?->id,
            'title'                 => $title,
            'description'           => trim((string) $request->input('description', '')),
            'instructions'          => trim((string) $request->input('instructions', '')) ?: null,
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

        $enrolledStudents = $this->assignmentRosterQuery($assignment)->with('user')->get();
        $rosterStudentIds = $enrolledStudents->pluck('id');

        $submissions = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->whereIn('student_id', $rosterStudentIds)
            ->with(['student.user'])
            ->latest('submitted_at')
            ->get();

        $submittedIds = $submissions->pluck('student_id')->toArray();
        $notSubmitted = $enrolledStudents->whereNotIn('id', $submittedIds);
        $canGradeSubmissions = $this->activeTeacher()?->status === 'active';

        return view('teacher.assignments.submissions', compact('assignment', 'submissions', 'notSubmitted', 'canGradeSubmissions'));
    }

    private function rosterSubmissionCount(Assignment $assignment): int
    {
        return AssignmentSubmission::where('assignment_id', $assignment->id)
            ->whereIn('student_id', $this->assignmentRosterQuery($assignment)->select('students.id'))
            ->count();
    }

    public function grade(Request $request, AssignmentSubmission $submission)
    {
        $this->ensureActiveTeacher();
        abort_unless($submission->assignment && $submission->assignment->created_by === auth()->id(), 403);
        abort_unless($this->assignmentRosterQuery($submission->assignment)->whereKey($submission->student_id)->exists(), 403);

        if ($submission->status === 'graded') {
            return back()->with('error', 'This submission is already graded and cannot be changed through the standard grading route.');
        }

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
