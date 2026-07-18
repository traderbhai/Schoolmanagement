<?php
namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{AcademicPmcCourseGroup, AcademicPmcElectiveChoice, AcademicPmcTimetableGenerationItem, Program, Subject, ProgramSubject, Term, Teacher, SubjectFacultyAssignment,
                ElectiveRegistrationWindow, Department, PmcAssessmentComponentConfig, TimetableEntry};
use Illuminate\Support\Collection;
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
                ->get();
        }

        $curriculumUsage = collect($programSubjects)
            ->mapWithKeys(fn (ProgramSubject $programSubject): array => [
                $programSubject->id => $this->curriculumSubjectUsage($programSubject),
            ]);

        $allSubjects = Subject::orderBy('name')->get();

        return view('departmental.program-chair.curriculum.index', compact(
            'programs', 'terms', 'selectedProgram', 'programSubjects', 'allSubjects', 'curriculumUsage'
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

        $this->authorizeProgramScope((int) $request->program_id);
        $this->validateSubjectForProgram((int) $request->subject_id, (int) $request->program_id);

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
        $this->authorizeProgramScope((int) $programSubject->program_id);

        $usage = $this->curriculumSubjectUsage($programSubject);

        if ($usage['locked']) {
            return back()->with('error', 'Curriculum subject is locked because it is already connected to ' . implode(', ', $usage['labels']) . '. Use a formal curriculum revision instead of deleting the source row.');
        }

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

        $workload = $this->officialTeacherWorkloadMap($currentTerm, $programIds);

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

        $this->authorizeProgramScope((int) $request->program_id);
        $this->validateSubjectForProgram((int) $request->subject_id, (int) $request->program_id);

        if (! ProgramSubject::where('program_id', $request->program_id)
            ->where('subject_id', $request->subject_id)
            ->where('term_id', $request->term_id)
            ->where('is_active', true)
            ->exists()) {
            return back()->withErrors(['subject_id' => 'Faculty can be assigned only to an active curriculum subject in the selected program and term.']);
        }

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
        $this->authorizeProgramScope((int) $assignment->program_id);
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

        $this->authorizeProgramScope((int) $request->program_id);

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
        $this->authorizeProgramScope((int) $window->program_id);

        if ($window->status === 'finalized' && $request->status !== 'finalized') {
            return back()->with('error', 'Finalized elective windows are locked. Create a new add/drop or revision window instead.');
        }

        if ($window->status === 'closed' && $request->status === 'open' && $this->windowHasSubmittedChoices($window)) {
            return back()->with('error', 'Closed elective windows with submitted choices cannot be reopened. Create a new revision window instead.');
        }

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
            ->with(['subject', 'subject.assessmentComponentConfigs' => fn($q) =>
                $q->when($currentTerm, fn($q2) => $q2->where('term_id', $currentTerm->id))
            ])
            ->get();

        return view('departmental.program-chair.curriculum.assessment', compact(
            'programs', 'terms', 'selectedProgram', 'currentTerm', 'programSubjects'
        ));
    }

    public function saveAssessmentComponent(Request $request) {
        $request->validate([
            'program_subject_id' => 'required|exists:program_subjects,id',
            'subject_id'  => 'required|exists:subjects,id',
            'term_id'     => 'required|exists:terms,id',
            'name'        => 'required|string|max:100',
            'max_marks'   => 'required|numeric|min:1|max:200',
            'weightage'   => 'required|numeric|min:1|max:100',
        ]);

        $programSubject = ProgramSubject::with(['program', 'subject', 'term'])
            ->whereKey($request->program_subject_id)
            ->firstOrFail();

        if (! in_array((int) $programSubject->program_id, array_map('intval', $this->programIds()), true)) {
            abort(403);
        }

        if ((int) $programSubject->subject_id !== (int) $request->subject_id
            || (int) $programSubject->term_id !== (int) $request->term_id) {
            return back()->with('error', 'Assessment component must belong to the selected curriculum subject and term.');
        }

        $existingTotal = PmcAssessmentComponentConfig::where('subject_id', $request->subject_id)
            ->where('term_id', $request->term_id)
            ->where('name', '!=', $request->name)
            ->sum('weightage');

        if (($existingTotal + (float) $request->weightage) > 100.0) {
            return back()->withInput()->withErrors([
                'weightage' => 'Assessment component weightage cannot exceed 100% for this subject and term.',
            ]);
        }

        PmcAssessmentComponentConfig::updateOrCreate(
            [
                'subject_id' => $request->subject_id,
                'term_id'    => $request->term_id,
                'name'       => $request->name,
            ],
            [
                'program_subject_id' => $programSubject->id,
                'program_id' => $programSubject->program_id,
                'max_marks' => $request->max_marks,
                'weightage' => $request->weightage,
                'created_by' => Auth::id(),
            ]
        );

        return back()->with('success', 'Assessment component saved.');
    }

    private function authorizeProgramScope(int $programId): void
    {
        abort_unless(in_array($programId, array_map('intval', $this->programIds()), true), 403);
    }

    private function validateSubjectForProgram(int $subjectId, int $programId): void
    {
        $subject = Subject::findOrFail($subjectId);
        abort_unless($subject->is_active && (int) $subject->program_id === $programId, 422, 'Selected subject must be active and belong to the selected program.');
    }

    private function officialTeacherWorkloadMap(?Term $term, array $programIds): Collection
    {
        if (! $term) {
            return collect();
        }

        $officialItems = AcademicPmcTimetableGenerationItem::with(['courseGroup'])
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereNotNull('teacher_id')
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->whereHas('timetableVersion', fn ($version) => $version->where('status', 'published'))
            ->where(function ($query) use ($term) {
                $query->where('term_id', $term->id)
                    ->orWhereHas('courseGroup', fn ($group) => $group->where('term_id', $term->id));
            })
            ->where(function ($query) use ($programIds) {
                $query->whereIn('program_id', $programIds)
                    ->orWhereHas('courseGroup', fn ($group) => $group->whereIn('program_id', $programIds));
            })
            ->get();

        $canonicalProgramTermKeys = $officialItems
            ->map(fn (AcademicPmcTimetableGenerationItem $item) => $this->programTermKey(
                $item->program_id ?? $item->courseGroup?->program_id,
                $item->term_id ?? $item->courseGroup?->term_id
            ))
            ->unique()
            ->values();

        $canonicalRows = $officialItems
            ->groupBy('teacher_id')
            ->map(fn (Collection $items): int => (int) $items->sum(fn (AcademicPmcTimetableGenerationItem $item) => max(1, (int) ($item->duration_slots ?? 1))));

        $legacyRows = TimetableEntry::where('term_id', $term->id)
            ->whereIn('program_id', $programIds)
            ->where(fn ($query) => $this->publishedTimetableScope($query))
            ->get(['teacher_id', 'program_id', 'term_id'])
            ->reject(fn (TimetableEntry $entry) => $canonicalProgramTermKeys->contains($this->programTermKey($entry->program_id, $entry->term_id)))
            ->groupBy('teacher_id')
            ->map(fn (Collection $entries): int => $entries->count());

        $workload = $canonicalRows->map(fn ($sessions): int => (int) $sessions);

        $legacyRows->each(function (int $sessions, int|string $teacherId) use (&$workload): void {
            $workload->put($teacherId, (int) ($workload->get($teacherId, 0) + $sessions));
        });

        return $workload;
    }

    private function publishedTimetableScope($query)
    {
        return $query
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function ($versionQuery) {
                $versionQuery->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn ($version) => $version->where('status', 'published'));
            });
    }

    private function programTermKey(mixed $programId, mixed $termId): string
    {
        return ((string) ($programId ?? 'none')) . ':' . ((string) ($termId ?? 'none'));
    }

    private function curriculumSubjectUsage(ProgramSubject $programSubject): array
    {
        $programId = (int) $programSubject->program_id;
        $subjectId = (int) $programSubject->subject_id;
        $termId = (int) $programSubject->term_id;

        $usage = [
            'published timetable sessions' => AcademicPmcTimetableGenerationItem::where('program_id', $programId)
                ->where('subject_id', $subjectId)
                ->where('term_id', $termId)
                ->whereIn('official_status', ['published', 'locked'])
                ->whereIn('status', ['scheduled', 'published', 'locked'])
                ->whereHas('timetableVersion', fn ($version) => $version->where('status', 'published'))
                ->exists(),
            'published legacy timetable rows' => TimetableEntry::where('program_id', $programId)
                ->where('subject_id', $subjectId)
                ->where('term_id', $termId)
                ->where(fn ($query) => $this->publishedTimetableScope($query))
                ->exists(),
            'course groups' => AcademicPmcCourseGroup::where('program_id', $programId)
                ->where('subject_id', $subjectId)
                ->where('term_id', $termId)
                ->whereIn('status', ['active', 'ready', 'locked', 'approved'])
                ->exists(),
            'faculty assignments' => SubjectFacultyAssignment::where('program_id', $programId)
                ->where('subject_id', $subjectId)
                ->where('term_id', $termId)
                ->exists(),
            'student enrollments' => \App\Models\StudentSubjectEnrollment::where('subject_id', $subjectId)
                ->where('term_id', $termId)
                ->whereIn('status', ['active', 'approved', 'allocated', 'locked'])
                ->exists(),
        ];

        $labels = collect($usage)->filter()->keys()->values()->all();

        return [
            'locked' => ! empty($labels),
            'labels' => $labels,
            'summary' => empty($labels) ? 'No downstream usage found.' : implode(', ', $labels),
        ];
    }

    private function windowHasSubmittedChoices(ElectiveRegistrationWindow $window): bool
    {
        return AcademicPmcElectiveChoice::where('program_id', $window->program_id)
            ->where('term_id', $window->term_id)
            ->whereIn('status', ['submitted', 'allocated', 'waitlisted'])
            ->exists();
    }
}
