<?php
namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{Program, Subject, ProgramSubject, Term, Teacher, SubjectFacultyAssignment,
                ElectiveRegistrationWindow, Department, AssessmentComponent};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PmcCurriculumController extends Controller {

    private function programIds(): array {
        return \App\Models\RoleProgramAssignment::where('user_id', Auth::id())
            ->where('is_active', true)->pluck('program_id')->toArray()
            ?: Program::where('is_active', true)->pluck('id')->toArray();
    }

    // ── Curriculum: list program subjects by term ─────────────────────────────
    public function index(Request $request) {
        $programIds = $this->programIds();
        $programs   = Program::whereIn('id', $programIds)->orderBy('name')->get();
        $terms      = Term::orderBy('start_date')->get();

        $selectedProgram = $request->filled('program_id')
            ? $programs->firstWhere('id', $request->program_id)
            : $programs->first();

        $programSubjects = [];
        if ($selectedProgram) {
            $programSubjects = ProgramSubject::where('program_id', $selectedProgram->id)
                ->with('subject')
                ->when($request->filled('term_id'), fn($q) => $q->where('term_id', $request->term_id))
                ->orderBy('term_id')
                ->get()
                ->groupBy('term_id');
        }

        $allSubjects = Subject::orderBy('name')->get();

        return view('departmental.program-chair.curriculum.index', compact(
            'programs', 'terms', 'selectedProgram', 'programSubjects', 'allSubjects'
        ));
    }

    // ── Add subject to program-term ───────────────────────────────────────────
    public function addSubject(Request $request) {
        $request->validate([
            'program_id'          => 'required|exists:programs,id',
            'subject_id'          => 'required|exists:subjects,id',
            'term_id'             => 'required|exists:terms,id',
            'type'                => 'required|in:compulsory,elective,lab,project,audit,open_elective',
            'elective_group'      => 'nullable|integer|min:1|max:20',
            'credits'             => 'nullable|integer|min:1|max:10',
            'max_elective_choices'=> 'nullable|integer|min:1',
        ]);

        $existing = ProgramSubject::where([
            'program_id' => $request->program_id,
            'subject_id' => $request->subject_id,
            'term_id'    => $request->term_id,
        ])->first();

        if ($existing) {
            return back()->with('error', 'This subject is already in the program for that term.');
        }

        ProgramSubject::create([
            'program_id'           => $request->program_id,
            'subject_id'           => $request->subject_id,
            'term_id'              => $request->term_id,
            'type'                 => $request->type,
            'elective_group'       => $request->elective_group,
            'credits'              => $request->credits,
            'max_elective_choices' => $request->max_elective_choices,
            'is_active'            => true,
        ]);

        return back()->with('success', 'Subject added to curriculum.');
    }

    // ── Remove subject from program-term ─────────────────────────────────────
    public function removeSubject(ProgramSubject $programSubject) {
        $programSubject->delete();
        return back()->with('success', 'Subject removed from curriculum.');
    }

    // ── Subject-faculty assignment ────────────────────────────────────────────
    public function assignments(Request $request) {
        $programIds = $this->programIds();
        $programs   = Program::whereIn('id', $programIds)->orderBy('name')->get();
        $terms      = Term::orderBy('start_date', 'desc')->take(6)->get();
        $batches    = \App\Models\Batch::whereIn('program_id', $programIds)->orderBy('name')->get();

        $selectedProgram = $request->filled('program_id')
            ? Program::find($request->program_id)
            : $programs->first();

        $currentTerm = $request->filled('term_id')
            ? Term::find($request->term_id)
            : Term::latest('start_date')->first();

        $assignments = SubjectFacultyAssignment::where('program_id', $selectedProgram?->id ?? 0)
            ->when($currentTerm, fn($q) => $q->where('term_id', $currentTerm->id))
            ->when($request->filled('batch_id'), fn($q) => $q->where('batch_id', $request->batch_id))
            ->with(['subject', 'teacher.user', 'batch'])
            ->get();

        // Subjects in this program-term
        $programSubjects = ProgramSubject::where('program_id', $selectedProgram?->id ?? 0)
            ->when($currentTerm, fn($q) => $q->where('term_id', $currentTerm->id))
            ->with('subject')
            ->get();

        // Available teachers
        $teachers = Teacher::with('user')->where('status', 'active')->orderBy('id')->get();

        // Workload per teacher this term (entries count)
        $workload = \App\Models\TimetableEntry::where('term_id', $currentTerm?->id)
            ->selectRaw('teacher_id, COUNT(*) as sessions')
            ->groupBy('teacher_id')
            ->pluck('sessions', 'teacher_id');

        return view('departmental.program-chair.curriculum.assignments', compact(
            'programs', 'terms', 'batches', 'selectedProgram', 'currentTerm',
            'assignments', 'programSubjects', 'teachers', 'workload'
        ));
    }

    public function assignFaculty(Request $request) {
        $request->validate([
            'subject_id'  => 'required|exists:subjects,id',
            'teacher_id'  => 'required|exists:teachers,id',
            'term_id'     => 'required|exists:terms,id',
            'batch_id'    => 'nullable|exists:batches,id',
            'program_id'  => 'required|exists:programs,id',
            'is_primary'  => 'boolean',
        ]);

        SubjectFacultyAssignment::updateOrCreate(
            [
                'subject_id' => $request->subject_id,
                'teacher_id' => $request->teacher_id,
                'term_id'    => $request->term_id,
                'batch_id'   => $request->batch_id,
            ],
            [
                'program_id'  => $request->program_id,
                'is_primary'  => $request->boolean('is_primary', true),
                'assigned_by' => Auth::id(),
            ]
        );

        return back()->with('success', 'Faculty assigned to subject.');
    }

    public function unassignFaculty(SubjectFacultyAssignment $assignment) {
        $assignment->delete();
        return back()->with('success', 'Assignment removed.');
    }

    // ── Elective management ───────────────────────────────────────────────────
    public function electives(Request $request) {
        $programIds = $this->programIds();
        $programs   = Program::whereIn('id', $programIds)->orderBy('name')->get();
        $terms      = Term::orderBy('start_date', 'desc')->take(6)->get();

        $selectedProgram = $request->filled('program_id')
            ? Program::find($request->program_id)
            : $programs->first();

        $currentTerm = $request->filled('term_id')
            ? Term::find($request->term_id)
            : Term::latest('start_date')->first();

        // Elective subjects in this program-term
        $electiveSubjects = ProgramSubject::where('program_id', $selectedProgram?->id ?? 0)
            ->when($currentTerm, fn($q) => $q->where('term_id', $currentTerm->id))
            ->whereIn('type', ['elective','open_elective'])
            ->with('subject')
            ->orderBy('elective_group')
            ->get();

        // Demand counts per subject
        $demand = \App\Models\StudentSubjectEnrollment::whereIn(
            'subject_id', $electiveSubjects->pluck('subject_id')
        )
        ->when($currentTerm, fn($q) => $q->where('term_id', $currentTerm->id))
        ->selectRaw('subject_id, COUNT(*) as cnt')
        ->groupBy('subject_id')
        ->pluck('cnt', 'subject_id');

        // Registration windows
        $windows = ElectiveRegistrationWindow::where('program_id', $selectedProgram?->id ?? 0)
            ->when($currentTerm, fn($q) => $q->where('term_id', $currentTerm->id))
            ->orderByDesc('id')
            ->get();

        return view('departmental.program-chair.curriculum.electives', compact(
            'programs', 'terms', 'selectedProgram', 'currentTerm',
            'electiveSubjects', 'demand', 'windows'
        ));
    }

    public function createWindow(Request $request) {
        $request->validate([
            'program_id'     => 'required|exists:programs,id',
            'term_id'        => 'required|exists:terms,id',
            'opens_at'       => 'required|date',
            'closes_at'      => 'required|date|after:opens_at',
            'max_selections' => 'required|integer|min:1|max:10',
            'instructions'   => 'nullable|string|max:1000',
        ]);

        ElectiveRegistrationWindow::create([
            'program_id'     => $request->program_id,
            'term_id'        => $request->term_id,
            'opens_at'       => $request->opens_at,
            'closes_at'      => $request->closes_at,
            'max_selections' => $request->max_selections,
            'status'         => 'draft',
            'instructions'   => $request->instructions,
            'created_by'     => Auth::id(),
        ]);

        return back()->with('success', 'Registration window created.');
    }

    public function updateWindowStatus(Request $request, ElectiveRegistrationWindow $window) {
        $request->validate(['status' => 'required|in:draft,open,closed,finalized']);
        $window->update(['status' => $request->status]);
        return back()->with('success', 'Window status updated to ' . $request->status . '.');
    }

    // ── Assessment components setup ───────────────────────────────────────────
    public function assessmentSetup(Request $request) {
        $programIds = $this->programIds();
        $programs   = Program::whereIn('id', $programIds)->orderBy('name')->get();
        $terms      = Term::orderBy('start_date', 'desc')->take(6)->get();

        $selectedProgram = $request->filled('program_id')
            ? Program::find($request->program_id)
            : $programs->first();

        $currentTerm = $request->filled('term_id')
            ? Term::find($request->term_id)
            : Term::latest('start_date')->first();

        $programSubjects = ProgramSubject::where('program_id', $selectedProgram?->id ?? 0)
            ->when($currentTerm, fn($q) => $q->where('term_id', $currentTerm->id))
            ->with(['subject', 'subject.assessmentComponents' => fn($q) =>
                $q->when($currentTerm, fn($q2) => $q2->where('term_id', $currentTerm->id))
            ])
            ->get();

        return view('departmental.program-chair.curriculum.assessment', compact(
            'programs', 'terms', 'selectedProgram', 'currentTerm', 'programSubjects'
        ));
    }

    public function saveAssessmentComponent(Request $request) {
        $request->validate([
            'subject_id'  => 'required|exists:subjects,id',
            'term_id'     => 'required|exists:terms,id',
            'name'        => 'required|string|max:100',
            'max_marks'   => 'required|numeric|min:1|max:200',
            'weightage'   => 'required|numeric|min:1|max:100',
        ]);

        AssessmentComponent::updateOrCreate(
            [
                'subject_id' => $request->subject_id,
                'term_id'    => $request->term_id,
                'name'       => $request->name,
            ],
            [
                'max_marks' => $request->max_marks,
                'weightage' => $request->weightage,
            ]
        );

        return back()->with('success', 'Assessment component saved.');
    }
}
