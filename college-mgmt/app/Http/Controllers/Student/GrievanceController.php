<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{StudentGrievance, GrievanceComment, Student};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GrievanceController extends Controller {
    private function student(): Student { return Student::where('user_id', Auth::id())->firstOrFail(); }

    public function index() {
        $student = $this->student();
        $grievances = StudentGrievance::where('student_id', $student->id)->latest()->get();
        $grievancePriority = $this->grievancePriority($grievances);

        return view('student.grievances.index', compact('grievances', 'grievancePriority'));
    }

    public function create()
    {
        $student = $this->student();
        if ($student->status !== 'active') {
            return redirect()
                ->route('student.grievances.index')
                ->with('error', 'New grievances can be submitted only by active students.');
        }

        return view('student.grievances.create');
    }

    public function store(Request $request) {
        $student = $this->student();
        if ($student->status !== 'active') {
            return redirect()
                ->route('student.grievances.index')
                ->with('error', 'New grievances can be submitted only by active students.');
        }

        $v = $request->validate([
            'category'    => 'required|in:academic,financial,facility,faculty,administrative,other',
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'priority'    => 'required|in:low,normal,high,urgent',
        ]);
        StudentGrievance::create(array_merge($v, [
            'student_id' => $student->id,
            'program_id' => $student->program_id,
            'status'     => 'open',
        ]));
        return redirect()->route('student.grievances.index')->with('success', 'Grievance submitted.');
    }

    public function show(StudentGrievance $grievance) {
        $student = $this->student();
        abort_if($grievance->student_id !== $student->id, 403);
        $grievance->load('comments.user');
        return view('student.grievances.show', compact('grievance'));
    }

    public function addComment(Request $request, StudentGrievance $grievance) {
        $student = $this->student();
        abort_if($grievance->student_id !== $student->id, 403);
        abort_unless($grievance->isOpen(), 422, 'Cannot comment on a closed grievance.');

        $request->validate(['comment' => 'required|string|max:2000']);
        GrievanceComment::create([
            'student_grievance_id' => $grievance->id,
            'user_id'              => Auth::id(),
            'comment'              => $request->comment,
        ]);
        return back()->with('success', 'Comment added.');
    }

    public function close(StudentGrievance $grievance) {
        $student = $this->student();
        abort_if($grievance->student_id !== $student->id, 403);
        abort_unless($grievance->status === 'resolved', 422, 'Only resolved grievances can be closed by the student.');

        $grievance->update(['status' => 'closed']);
        return back()->with('success', 'Grievance closed.');
    }

    private function grievancePriority($grievances): array {
        $escalated = $grievances->firstWhere('status', 'escalated');
        if ($escalated) {
            return [
                'level' => 'danger',
                'title' => 'A grievance has been escalated',
                'body' => 'Your escalated grievance is visible to senior academic staff. Add any missing evidence in the comment thread.',
                'route' => route('student.grievances.show', $escalated),
                'action' => 'Open Grievance',
            ];
        }

        $open = $grievances->first(fn($g) => in_array($g->status, ['open', 'under_review'], true));
        if ($open) {
            return [
                'level' => 'info',
                'title' => 'Track your open grievance',
                'body' => 'Follow staff updates, add clarifications, and close the grievance only after the issue is resolved.',
                'route' => route('student.grievances.show', $open),
                'action' => 'Track Status',
            ];
        }

        $resolved = $grievances->firstWhere('status', 'resolved');
        if ($resolved) {
            return [
                'level' => 'success',
                'title' => 'Review resolved grievance',
                'body' => 'Read the resolution notes and close the grievance if the issue has been addressed.',
                'route' => route('student.grievances.show', $resolved),
                'action' => 'Review Resolution',
            ];
        }

        return [
            'level' => 'none',
            'title' => 'No active grievance follow-up',
            'body' => 'Submit a grievance when you need help with academic, financial, facility, faculty, or administrative issues.',
            'route' => route('student.grievances.create'),
            'action' => 'Submit Grievance',
        ];
    }
}
