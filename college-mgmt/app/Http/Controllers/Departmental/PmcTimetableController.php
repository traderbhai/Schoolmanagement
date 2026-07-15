<?php
namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{AcademicPmcCourseGroup, AcademicPmcFacultyAssignmentAcknowledgement, AcademicPmcRoomReadinessReview, AcademicPmcSubstitutionRecommendation, AcademicPmcTimetableChangeRequest, AcademicPmcTimetableGenerationItem, AcademicPmcTimetableGenerationRun, AcademicPmcTimetableImpactRecord, AcademicPmcTimetablePublishCheck, AcademicYear, Course, Department, Program, Term, Batch, TimetableEntry, TimetableSlot, TimetableVersion,
                TimetableSubstitution, TeacherAvailability, Subject, Teacher, Classroom,
                RoleProgramAssignment, Semester};
use App\Services\{AcademicPmcTimetableV041Service, AutoSchedulingService, CanonicalTimetableBridgeService, ConflictPreventionService, TeacherWorkloadWarningService, TimetableConflictService, TimetableCopyService, TimetableImportService, TimetablePdfService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PmcTimetableController extends Controller {

    public function __construct(private CanonicalTimetableBridgeService $canonicalBridge) {}

    private function programIds(): array {
        $user = Auth::user();
        if ($user?->hasRole(['admin', 'dean_academics', 'director', 'academic_department_owner'])) {
            return Program::where('is_active', true)->pluck('id')->toArray();
        }

        $ids = RoleProgramAssignment::where('user_id', Auth::id())
            ->where('is_active', true)->pluck('program_id')->toArray();

        return array_values(array_filter(array_map('intval', $ids)));
    }

    private function authorizeProgramScope(int $programId): void
    {
        abort_unless(in_array($programId, $this->programIds(), true), 403);
    }

    private function hasAcademicOversight(): bool
    {
        return Auth::user()?->hasRole(['admin', 'dean_academics', 'director', 'academic_department_owner']) ?? false;
    }

    private function validateAcademicScope(Request $request): ?string
    {
        $programId = (int) $request->program_id;
        $this->authorizeProgramScope($programId);

        $term = Term::find($request->term_id);
        if (! $term || (int) $term->program_id !== $programId) {
            return 'Selected term does not belong to the selected program.';
        }

        if ($request->filled('batch_id')) {
            $batch = Batch::find($request->batch_id);
            if (! $batch || (int) $batch->program_id !== $programId) {
                return 'Selected batch does not belong to the selected program.';
            }
        }

        if ($request->filled('subject_id')) {
            $subject = Subject::find($request->subject_id);
            if (! $subject || ($subject->program_id !== null && (int) $subject->program_id !== $programId)) {
                return 'Selected subject does not belong to the selected program.';
            }
        }

        return null;
    }

    private function hasPublishedVersion(int $programId, int $termId, ?int $batchId = null): bool
    {
        return TimetableVersion::where('program_id', $programId)
            ->where('term_id', $termId)
            ->where('status', 'published')
            ->when($batchId, fn($query) => $query->where('batch_id', $batchId), fn($query) => $query->whereNull('batch_id'))
            ->exists();
    }

    private function blockIfPublished(int $programId, int $termId, ?int $batchId = null)
    {
        if ($this->hasPublishedVersion($programId, $termId, $batchId)) {
            return back()->with('error', $this->publishedLockMessage());
        }

        return null;
    }

    private function publishedLockMessage(): string
    {
        return 'Published timetable history is locked on legacy Program Chair routes. Use PMC timetable revision/version workflow for changes.';
    }

    private function canonicalPublishBlockers(int $programId, int $termId, ?int $batchId = null): array
    {
        $run = AcademicPmcTimetableGenerationRun::where('program_id', $programId)
            ->where('term_id', $termId)
            ->when($batchId, fn ($query) => $query->where('batch_id', $batchId), fn ($query) => $query->whereNull('batch_id'))
            ->latest()
            ->first();

        if (! $run) {
            return ['Generate a canonical PMC timetable run before publishing.'];
        }

        $blockers = [];
        if ((int) $run->unscheduled_count > 0) {
            $blockers[] = "{$run->unscheduled_count} required session(s) are unscheduled.";
        }
        if ((int) $run->hard_conflict_count > 0) {
            $blockers[] = "{$run->hard_conflict_count} hard conflict(s) remain.";
        }

        $publishChecks = AcademicPmcTimetablePublishCheck::where('generation_run_id', $run->id)
            ->whereIn('status', ['block', 'blocked', 'pending', 'open'])
            ->pluck('title')
            ->filter()
            ->values()
            ->all();
        foreach ($publishChecks as $check) {
            $blockers[] = 'Publish check blocked: ' . $check;
        }

        $roomBlockers = AcademicPmcRoomReadinessReview::where('generation_run_id', $run->id)
            ->where(fn ($query) => $query->whereIn('status', ['review_required', 'revision_required', 'rejected'])
                ->orWhereIn('readiness_band', ['blocked', 'warning']))
            ->count();
        if ($roomBlockers > 0) {
            $blockers[] = "{$roomBlockers} room/lab readiness review(s) require approval.";
        }

        $impactPreviewCount = AcademicPmcTimetableImpactRecord::where('metadata->generation_run_id', $run->id)->count();
        if ((int) $run->scheduled_count > 0 && $impactPreviewCount === 0) {
            $blockers[] = 'Impact preview must be refreshed before publish.';
        }

        $groupIds = AcademicPmcTimetableGenerationItem::where('generation_run_id', $run->id)
            ->pluck('course_group_id')
            ->filter()
            ->unique()
            ->values();
        if ($groupIds->isNotEmpty()) {
            $openAcknowledgements = AcademicPmcFacultyAssignmentAcknowledgement::whereHas('assignment', fn ($query) => $query->whereIn('course_group_id', $groupIds))
                ->whereIn('status', ['pending', 'requested', 'concern_raised', 'declined', 'revision_required'])
                ->count();
            if ($openAcknowledgements > 0) {
                $blockers[] = "{$openAcknowledgements} faculty acknowledgement(s) are not cleared.";
            }
        }

        return $blockers;
    }

    private function validateProgramTermBatchScope(int $programId, int $termId, ?int $batchId = null): ?string
    {
        $this->authorizeProgramScope($programId);

        $term = Term::find($termId);
        if (! $term || (int) $term->program_id !== $programId) {
            return 'Selected term does not belong to the selected program.';
        }

        if ($batchId !== null) {
            $batch = Batch::find($batchId);
            if (! $batch || (int) $batch->program_id !== $programId) {
                return 'Selected batch does not belong to the selected program.';
            }
        }

        return null;
    }

    private function legacySemesterForTerm(Term $term): Semester
    {
        $semester = Semester::where('number', $term->term_number)->first()
            ?: Semester::where('name', $term->name)->first()
            ?: Semester::current()
            ?: Semester::first();

        if ($semester) {
            return $semester;
        }

        $year = AcademicYear::current() ?: AcademicYear::firstOrCreate(
            ['name' => now()->year . '-' . now()->addYear()->year],
            [
                'start_year' => now()->year,
                'end_year' => now()->addYear()->year,
                'start_date' => now()->startOfYear()->toDateString(),
                'end_date' => now()->addYear()->endOfYear()->toDateString(),
                'is_current' => true,
            ]
        );

        return Semester::create([
            'academic_year_id' => $year->id,
            'name' => $term->name ?: 'PMC Legacy Term',
            'number' => $term->term_number ?: 1,
            'start_date' => $term->start_date ?: now()->startOfMonth()->toDateString(),
            'end_date' => $term->end_date ?: now()->addMonths(4)->endOfMonth()->toDateString(),
            'is_current' => false,
        ]);
    }

    private function legacyCourseForProgram(Program $program): Course
    {
        return Course::firstOrCreate(
            ['code' => 'PMCP' . $program->id],
            [
                'department_id' => $program->department_id,
                'name' => 'PMC Timetable Bridge - ' . $program->name,
                'description' => 'Compatibility bridge for legacy timetable rows created from PMC program timetable workflows.',
                'duration_years' => max(1, (int) ($program->duration_years ?: 1)),
                'total_semesters' => max(1, (int) ($program->total_terms ?: 1)),
                'is_active' => true,
            ]
        );
    }

    private function legacyCourseForCourseGroup(AcademicPmcCourseGroup $group, Program $program): Course
    {
        $departmentId = $group->subject?->department_id
            ?: $program->department_id
            ?: Department::query()->value('id');

        if (! $departmentId) {
            $departmentId = Department::firstOrCreate(['code' => 'ACAD'], ['name' => 'Academics'])->id;
        }

        return Course::firstOrCreate(
            ['code' => 'PMCG' . $group->id],
            [
                'department_id' => $departmentId,
                'name' => 'PMC Group ' . $group->name,
                'description' => 'Compatibility bridge for PMC timetable group #' . $group->id,
                'duration_years' => 1,
                'total_semesters' => 1,
                'is_active' => true,
            ]
        );
    }

    // ── Timetable builder ─────────────────────────────────────────────────────
    public function builder(Request $request) {
        $programIds = $this->programIds();
        $programs   = Program::whereIn('id', $programIds)->orderBy('name')->get();
        $terms      = Term::orderBy('start_date', 'desc')->take(8)->get();
        $batches    = Batch::whereIn('program_id', $programIds)->orderBy('name')->get();

        $selectedProgram = $request->filled('program_id')
            ? Program::find($request->program_id) : $programs->first();

        $selectedTerm = $request->filled('term_id')
            ? Term::find($request->term_id) : Term::latest('start_date')->first();

        $selectedBatch = $request->filled('batch_id')
            ? Batch::find($request->batch_id) : null;
        $builderFilters = [
            'teacher_id' => $request->filled('teacher_id') ? (int) $request->teacher_id : null,
            'classroom_id' => $request->filled('classroom_id') ? (int) $request->classroom_id : null,
            'course_group_id' => $request->filled('course_group_id') ? (int) $request->course_group_id : null,
            'session_type' => $request->filled('session_type') ? (string) $request->session_type : null,
            'timetable_status' => $request->filled('timetable_status') ? (string) $request->timetable_status : null,
        ];

        $slots = TimetableSlot::where('is_active', true)->orderBy('sort_order')->get();
        $days  = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday'];

        // Legacy compatibility entries for this program-term-batch.
        $entries = TimetableEntry::where('term_id', $selectedTerm?->id)
            ->when($selectedProgram, fn($q) => $q->where('program_id', $selectedProgram->id))
            ->when($selectedBatch,   fn($q) => $q->where('batch_id', $selectedBatch->id))
            ->where('is_active', true)
            ->with(['subject','teacher.user','classroom','slot','batch'])
            ->get()
            ->keyBy(fn($e) => $e->day_of_week . '-' . $e->timetable_slot_id);

        $canonicalEntries = AcademicPmcTimetableGenerationItem::with(['subject', 'courseGroup.subject', 'courseGroup.batch', 'teacher.user', 'classroom', 'slot', 'batch'])
            ->whereNotIn('official_status', ['archived', 'cancelled'])
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->whereNotNull('day_of_week')
            ->whereNotNull('timetable_slot_id')
            ->when($selectedProgram, fn($q) => $q->where(function ($scope) use ($selectedProgram) {
                $scope->where('program_id', $selectedProgram->id)
                    ->orWhereHas('courseGroup', fn($group) => $group->where('program_id', $selectedProgram->id));
            }))
            ->when($selectedTerm, fn($q) => $q->where(function ($scope) use ($selectedTerm) {
                $scope->where('term_id', $selectedTerm->id)
                    ->orWhereHas('courseGroup', fn($group) => $group->where('term_id', $selectedTerm->id));
            }))
            ->when($selectedBatch, fn($q) => $q->where(function ($scope) use ($selectedBatch) {
                $scope->where('batch_id', $selectedBatch->id)
                    ->orWhereHas('courseGroup', fn($group) => $group->where('batch_id', $selectedBatch->id));
            }))
            ->when($builderFilters['teacher_id'], fn($q, $teacherId) => $q->where('teacher_id', $teacherId))
            ->when($builderFilters['classroom_id'], fn($q, $classroomId) => $q->where('classroom_id', $classroomId))
            ->when($builderFilters['course_group_id'], fn($q, $courseGroupId) => $q->where('course_group_id', $courseGroupId))
            ->when($builderFilters['session_type'], fn($q, $sessionType) => $q->where('session_type', $sessionType))
            ->when($builderFilters['timetable_status'], fn($q, $status) => $q->where('status', $status))
            ->orderBy('day_of_week')
            ->orderBy('timetable_slot_id')
            ->orderBy('id')
            ->get()
            ->groupBy(fn($item) => $item->day_of_week . '-' . $item->timetable_slot_id);

        // Subjects in this program-term
        $programSubjects = \App\Models\ProgramSubject::where('program_id', $selectedProgram?->id ?? 0)
            ->when($selectedTerm, fn($q) => $q->where('term_id', $selectedTerm->id))
            ->with('subject')
            ->get();

        $courseGroups = AcademicPmcCourseGroup::with(['subject', 'batch'])
            ->when($selectedProgram, fn($q) => $q->where('program_id', $selectedProgram->id))
            ->when($selectedTerm, fn($q) => $q->where('term_id', $selectedTerm->id))
            ->when($selectedBatch, fn($q) => $q->where('batch_id', $selectedBatch->id))
            ->whereIn('status', ['active', 'draft'])
            ->orderBy('name')
            ->get();

        $subjectOptions = $programSubjects
            ->pluck('subject')
            ->filter()
            ->merge($courseGroups->pluck('subject')->filter())
            ->unique('id')
            ->sortBy('name')
            ->values();

        $courseGroupsForBuilder = $courseGroups
            ->map(fn (AcademicPmcCourseGroup $group): array => [
                'id' => $group->id,
                'subject_id' => $group->subject_id,
                'batch_id' => $group->batch_id,
                'group_type' => $group->group_type,
            ])
            ->values();

        $teachers   = Teacher::with('user')->where('status','active')->orderBy('id')->get();
        $classrooms = Classroom::where('is_active', true)->orderBy('name')->get();

        // Teacher availability for this term
        $availability = TeacherAvailability::when($selectedTerm, fn($q) => $q->where('term_id', $selectedTerm->id))
            ->get()
            ->groupBy(fn($a) => $a->teacher_id . '-' . $a->day_of_week . '-' . $a->timetable_slot_id);

        // Version status
        $version = TimetableVersion::where('program_id', $selectedProgram?->id ?? 0)
            ->when($selectedTerm, fn($q) => $q->where('term_id', $selectedTerm->id))
            ->when($selectedBatch, fn($q) => $q->where('batch_id', $selectedBatch->id))
            ->latest()
            ->first();

        return view('departmental.program-chair.timetable.builder', compact(
            'programs','terms','batches','selectedProgram','selectedTerm','selectedBatch',
            'builderFilters','slots','days','entries','canonicalEntries','programSubjects','subjectOptions','courseGroups','courseGroupsForBuilder','teachers','classrooms','availability','version'
        ));
    }

    // ── Save a single slot assignment ─────────────────────────────────────────
    public function saveSlot(Request $request) {
        $request->validate([
            'program_id'        => 'required|exists:programs,id',
            'term_id'           => 'required|exists:terms,id',
            'batch_id'          => 'nullable|exists:batches,id',
            'day_of_week'       => 'required|integer|between:1,6',
            'timetable_slot_id' => 'required|exists:timetable_slots,id',
            'subject_id'        => 'nullable|exists:subjects,id',
            'teacher_id'        => 'nullable|exists:teachers,id',
            'classroom_id'      => 'nullable|exists:classrooms,id',
            'course_group_id'   => 'nullable|exists:academic_pmc_course_groups,id',
            'session_type'      => 'nullable|string|max:80',
            'duration_slots'    => 'nullable|integer|min:1|max:6',
        ]);

        if ($message = $this->validateAcademicScope($request)) {
            return response()->json(['message' => $message], 422);
        }

        if ($this->hasPublishedVersion((int) $request->program_id, (int) $request->term_id, $request->filled('batch_id') ? (int) $request->batch_id : null)) {
            return response()->json(['message' => $this->publishedLockMessage()], 423);
        }

        if ($request->filled('course_group_id')) {
            return $this->saveCanonicalCourseGroupSlot($request);
        }

        return response()->json([
            'message' => 'Canonical timetable editing requires a course group. Legacy timetable rows are now read-only compatibility bridges.',
        ], 422);
    }

    private function saveCanonicalCourseGroupSlot(Request $request)
    {
        $courseGroup = AcademicPmcCourseGroup::findOrFail($request->course_group_id);

        if (
            (int) $courseGroup->program_id !== (int) $request->program_id
            || (int) $courseGroup->term_id !== (int) $request->term_id
            || (int) $courseGroup->batch_id !== (int) $request->batch_id
            || ($request->filled('subject_id') && (int) $courseGroup->subject_id !== (int) $request->subject_id)
        ) {
            return response()->json(['message' => 'Course group does not match the selected program, term, batch, and subject.'], 422);
        }

        $existing = AcademicPmcTimetableGenerationItem::where('course_group_id', $courseGroup->id)
            ->where('day_of_week', $request->day_of_week)
            ->where('timetable_slot_id', $request->timetable_slot_id)
            ->get();

        $locked = $existing->first(fn (AcademicPmcTimetableGenerationItem $item): bool =>
            $item->official_status === 'published'
            || $item->timetable_version_id !== null
            || $item->is_locked
        );

        if ($locked) {
            return response()->json(['message' => 'Existing canonical timetable history for this group slot is locked. Use the PMC revision/version workflow.'], 423);
        }

        if ($request->filled('subject_id')) {
            $availability = app(ConflictPreventionService::class)->isSlotAvailable(
                (int) $request->day_of_week,
                (int) $request->timetable_slot_id,
                (int) $request->teacher_id,
                (int) $request->classroom_id,
                (int) $request->batch_id,
                (int) $request->term_id,
                $courseGroup->id,
                (int) ($request->duration_slots ?? $this->durationForCourseGroup($courseGroup)),
                $existing->pluck('id')->map(fn ($id) => (int) $id)->all()
            );

            if (! $availability['available']) {
                return response()->json(['conflicts' => $availability['conflicts']], 422);
            }
        }

        $existing->each->delete();

        if ($request->filled('subject_id')) {
            $run = AcademicPmcTimetableGenerationRun::create([
                'title' => 'Manual course-group slot edit',
                'strategy' => 'manual_slot_edit',
                'program_id' => $request->program_id,
                'batch_id' => $request->batch_id,
                'term_id' => $request->term_id,
                'created_by' => Auth::id(),
                'status' => 'draft',
                'scheduled_count' => 1,
                'unscheduled_count' => 0,
                'quality_score' => 0,
                'input_summary' => [
                    'source' => 'program_chair_save_slot',
                ],
            ]);

            AcademicPmcTimetableGenerationItem::create([
                'generation_run_id' => $run->id,
                'course_group_id' => $courseGroup->id,
                'program_id' => $request->program_id,
                'batch_id' => $request->batch_id,
                'term_id' => $request->term_id,
                'subject_id' => $request->subject_id,
                'session_index' => 1,
                'session_type' => $request->session_type ?? $this->sessionTypeForCourseGroup($courseGroup, []),
                'duration_slots' => $request->duration_slots ?? $this->durationForCourseGroup($courseGroup),
                'teacher_id' => $request->teacher_id,
                'classroom_id' => $request->classroom_id,
                'day_of_week' => $request->day_of_week,
                'timetable_slot_id' => $request->timetable_slot_id,
                'status' => 'scheduled',
                'official_status' => 'draft',
                'source_type' => 'manual_slot_edit',
                'metadata' => [
                    'edited_from' => 'program_chair_legacy_builder',
                    'group_type' => $courseGroup->group_type,
                ],
            ]);
        }

        return response()->json(['message' => 'Saved.']);
    }

    // ── Publish timetable ─────────────────────────────────────────────────────
    public function publish(Request $request) {
        $request->validate([
            'program_id'    => 'required|exists:programs,id',
            'term_id'       => 'required|exists:terms,id',
            'batch_id'      => 'nullable|exists:batches,id',
            'effective_from'=> 'nullable|date',
        ]);

        if ($message = $this->validateAcademicScope($request)) {
            return back()->with('error', $message);
        }

        if ($response = $this->blockIfPublished((int) $request->program_id, (int) $request->term_id, $request->filled('batch_id') ? (int) $request->batch_id : null)) {
            return $response;
        }

        // Run full conflict audit before publishing
        $conflicts = app(TimetableConflictService::class)->auditTerm(
            $request->term_id, $request->batch_id
        );

        if ($conflicts) {
            return back()->with('error', 'Cannot publish — conflicts found: ' . implode(' | ', array_slice($conflicts, 0, 3)));
        }

        $blockers = $this->canonicalPublishBlockers(
            (int) $request->program_id,
            (int) $request->term_id,
            $request->filled('batch_id') ? (int) $request->batch_id : null
        );

        if ($blockers !== []) {
            return back()->with('error', 'Cannot publish canonical timetable: ' . implode(' | ', array_slice($blockers, 0, 5)));
        }

        // Archive previous published version
        TimetableVersion::where([
            'program_id' => $request->program_id,
            'term_id'    => $request->term_id,
            'batch_id'   => $request->batch_id,
            'status'     => 'published',
        ])->update(['status' => 'archived']);

        // Create version record
        $lastVersion = TimetableVersion::where('program_id', $request->program_id)
            ->where('term_id', $request->term_id)
            ->max('version_number') ?? 0;

        $version = TimetableVersion::create([
            'program_id'     => $request->program_id,
            'term_id'        => $request->term_id,
            'batch_id'       => $request->batch_id,
            'version_number' => $lastVersion + 1,
            'status'         => 'published',
            'created_by'     => Auth::id(),
            'published_by'   => Auth::id(),
            'published_at'   => now(),
            'effective_from' => $request->effective_from ?? now()->toDateString(),
        ]);

        $this->archiveCanonicalItemsForPublishedScope(
            (int) $request->program_id,
            (int) $request->term_id,
            $request->filled('batch_id') ? (int) $request->batch_id : null
        );
        $canonicalItems = $this->canonicalPublishItems(
            (int) $request->program_id,
            (int) $request->term_id,
            $request->filled('batch_id') ? (int) $request->batch_id : null
        )->get();
        $this->publishCanonicalItems($canonicalItems, $version);
        $this->syncCanonicalItemsToLegacyRows($canonicalItems, $version);

        return back()->with('success', "Timetable published as version {$version->version_number}.");
    }

    private function canonicalPublishItems(int $programId, int $termId, ?int $batchId)
    {
        return AcademicPmcTimetableGenerationItem::with(['courseGroup.subject', 'subject', 'teacher', 'classroom', 'slot'])
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where(fn ($query) => $query->whereNull('official_status')->orWhere('official_status', '!=', 'archived'))
            ->whereNotNull('day_of_week')
            ->whereNotNull('timetable_slot_id')
            ->where(function ($query) use ($programId, $termId) {
                $query->where(function ($direct) use ($programId, $termId) {
                    $direct->where('program_id', $programId)
                        ->where('term_id', $termId);
                })->orWhereHas('courseGroup', function ($group) use ($programId, $termId) {
                    $group->where('program_id', $programId)
                        ->where('term_id', $termId);
                });
            })
            ->when($batchId, function ($query) use ($batchId) {
                $query->where(function ($scope) use ($batchId) {
                    $scope->where('batch_id', $batchId)
                        ->orWhereHas('courseGroup', fn ($group) => $group->where('batch_id', $batchId));
                });
            });
    }

    private function archiveCanonicalItemsForPublishedScope(int $programId, int $termId, ?int $batchId): void
    {
        $versionIds = TimetableVersion::where('program_id', $programId)
            ->where('term_id', $termId)
            ->when($batchId, fn ($query) => $query->where('batch_id', $batchId), fn ($query) => $query->whereNull('batch_id'))
            ->where('status', 'archived')
            ->pluck('id');

        if ($versionIds->isEmpty()) {
            return;
        }

        AcademicPmcTimetableGenerationItem::whereIn('timetable_version_id', $versionIds)
            ->where('official_status', 'published')
            ->update(['official_status' => 'archived', 'updated_at' => now()]);
    }

    private function publishCanonicalItems($items, TimetableVersion $version): void
    {
        foreach ($items as $item) {
            $group = $item->courseGroup;
            $item->update([
                'timetable_version_id' => $version->id,
                'program_id' => $item->program_id ?? $group?->program_id ?? $version->program_id,
                'batch_id' => $item->batch_id ?? $group?->batch_id ?? $version->batch_id,
                'term_id' => $item->term_id ?? $group?->term_id ?? $version->term_id,
                'subject_id' => $item->subject_id ?? $group?->subject_id,
                'official_status' => 'published',
                'published_at' => now(),
                'published_by' => Auth::id(),
                'metadata' => array_merge($item->metadata ?: [], [
                    'canonical_official_session' => true,
                    'official_source' => 'academic_pmc_timetable_generation_items',
                    'published_from' => 'program_chair_legacy_publish',
                    'timetable_version_id' => $version->id,
                    'published_by' => Auth::id(),
                    'published_at' => now()->toDateTimeString(),
                ]),
            ]);
        }
    }

    private function syncCanonicalItemsToLegacyRows($items, TimetableVersion $version): void
    {
        foreach ($items as $item) {
            $this->canonicalBridge->ensureBridgeForOfficialSession($item, Auth::user());
        }
    }

    // ── Conflict checker (AJAX) ───────────────────────────────────────────────
    public function checkConflict(Request $request) {
        if ($request->filled('course_group_id')) {
            $request->validate([
                'course_group_id' => 'required|exists:academic_pmc_course_groups,id',
                'teacher_id' => 'required|exists:teachers,id',
                'classroom_id' => 'required|exists:classrooms,id',
                'batch_id' => 'required|exists:batches,id',
                'term_id' => 'required|exists:terms,id',
                'day_of_week' => 'required|integer|between:1,6',
                'timetable_slot_id' => 'required|exists:timetable_slots,id',
                'duration_slots' => 'nullable|integer|min:1|max:6',
            ]);

            $existing = AcademicPmcTimetableGenerationItem::where('course_group_id', $request->course_group_id)
                ->where('day_of_week', $request->day_of_week)
                ->where('timetable_slot_id', $request->timetable_slot_id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $availability = app(ConflictPreventionService::class)->isSlotAvailable(
                (int) $request->day_of_week,
                (int) $request->timetable_slot_id,
                (int) $request->teacher_id,
                (int) $request->classroom_id,
                (int) $request->batch_id,
                (int) $request->term_id,
                (int) $request->course_group_id,
                (int) ($request->duration_slots ?? 1),
                $existing
            );

            return response()->json(['conflicts' => $availability['conflicts']]);
        }

        $conflicts = app(TimetableConflictService::class)->check($request->all());
        return response()->json(['conflicts' => $conflicts]);
    }

    // ── Substitutions ─────────────────────────────────────────────────────────
    public function substitutions(Request $request) {
        $programIds = $this->programIds();
        $currentTerm = Term::latest('start_date')->first();

        $canonicalSessions = AcademicPmcTimetableGenerationItem::with(['subject', 'courseGroup.subject', 'courseGroup.batch', 'teacher.user', 'classroom', 'slot', 'timetableVersion'])
            ->where(function ($query) use ($programIds) {
                $query->whereIn('program_id', $programIds)
                    ->orWhereHas('courseGroup', fn ($group) => $group->whereIn('program_id', $programIds));
            })
            ->where(function ($query) use ($currentTerm) {
                $query->where('term_id', $currentTerm?->id)
                    ->orWhereHas('courseGroup', fn ($group) => $group->where('term_id', $currentTerm?->id));
            })
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn ($version) => $version->where('status', 'published'))
            ->orderBy('day_of_week')
            ->orderBy('timetable_slot_id')
            ->get();

        $entries = TimetableEntry::whereIn('program_id', $programIds)
            ->where('term_id', $currentTerm?->id)
            ->where('is_active', true)
            ->with(['subject','teacher.user','slot','batch'])
            ->get();

        $recentLegacy = TimetableSubstitution::whereHas('entry', fn($q) => $q->whereIn('program_id', $programIds))
            ->with(['entry.subject','entry.batch','substitute.user','creator'])
            ->orderByDesc('date')
            ->take(30)
            ->get();

        $recentRecommendations = AcademicPmcSubstitutionRecommendation::with(['pmcGenerationItem.subject', 'courseGroup.subject', 'originalTeacher.user', 'substituteTeacher.user'])
            ->where(function ($query) use ($programIds) {
                $query->whereHas('pmcGenerationItem', fn ($item) => $item->whereIn('program_id', $programIds))
                    ->orWhereHas('courseGroup', fn ($group) => $group->whereIn('program_id', $programIds));
            })
            ->orderByDesc('substitution_date')
            ->orderByDesc('id')
            ->take(30)
            ->get();

        $recentChanges = AcademicPmcTimetableChangeRequest::with(['pmcGenerationItem.subject', 'pmcGenerationItem.courseGroup.subject', 'pmcGenerationItem.teacher.user'])
            ->whereIn('change_type', ['substitution', 'cancellation', 'reschedule'])
            ->whereHas('pmcGenerationItem', fn ($item) => $item
                ->whereIn('program_id', $programIds)
                ->orWhereHas('courseGroup', fn ($group) => $group->whereIn('program_id', $programIds)))
            ->orderByDesc('created_at')
            ->take(30)
            ->get();

        $recent = $recentRecommendations
            ->map(fn (AcademicPmcSubstitutionRecommendation $rec): array => [
                'date' => $rec->substitution_date,
                'session' => $rec->courseGroup?->subject?->name
                    ?? $rec->pmcGenerationItem?->subject?->name
                    ?? $rec->pmcGenerationItem?->courseGroup?->subject?->name
                    ?? 'Canonical session',
                'group' => $rec->courseGroup?->name ?? $rec->pmcGenerationItem?->courseGroup?->name,
                'action' => 'substitute',
                'status' => $rec->status,
                'substitute' => $rec->substituteTeacher?->user?->name ?? 'Uncovered',
                'reason' => collect($rec->reasons ?? [])->map(fn ($reason) => str_replace('_', ' ', (string) $reason))->join(', '),
                'source' => 'canonical',
            ])
            ->merge($recentChanges->map(fn (AcademicPmcTimetableChangeRequest $change): array => [
                'date' => $change->created_at,
                'session' => $change->pmcGenerationItem?->subject?->name
                    ?? $change->pmcGenerationItem?->courseGroup?->subject?->name
                    ?? 'Canonical session',
                'group' => $change->pmcGenerationItem?->courseGroup?->name,
                'action' => $change->change_type,
                'status' => $change->status,
                'substitute' => null,
                'reason' => $change->reason,
                'source' => 'canonical',
            ]))
            ->merge($recentLegacy->map(fn (TimetableSubstitution $sub): array => [
                'date' => $sub->date,
                'session' => $sub->entry?->subject?->name ?? 'Legacy session',
                'group' => $sub->entry?->batch?->name,
                'action' => $sub->action,
                'status' => 'recorded',
                'substitute' => $sub->substitute?->user?->name,
                'reason' => $sub->reason,
                'source' => 'legacy',
            ]))
            ->sortByDesc('date')
            ->take(30)
            ->values();

        $teachers = Teacher::with('user')->where('status','active')->get();

        return view('departmental.program-chair.timetable.substitutions', compact(
            'canonicalSessions', 'entries', 'recent', 'teachers', 'currentTerm'
        ));
    }

    public function createSubstitution(Request $request) {
        $request->validate([
            'session_ref'           => 'nullable|string|max:80',
            'timetable_entry_id'    => 'nullable|exists:timetable_entries,id',
            'date'                  => 'required|date',
            'action'                => 'required|in:substitute,cancelled,rescheduled',
            'substitute_teacher_id' => 'nullable|exists:teachers,id',
            'reason'                => 'nullable|string|max:300',
        ]);

        if ($request->filled('session_ref') && str_starts_with((string) $request->session_ref, 'pmc:')) {
            return $this->createCanonicalSubstitution($request, (int) str_replace('pmc:', '', (string) $request->session_ref));
        }

        if ($request->filled('session_ref') && str_starts_with((string) $request->session_ref, 'legacy:')) {
            $request->merge(['timetable_entry_id' => (int) str_replace('legacy:', '', (string) $request->session_ref)]);
        }

        if (! $request->filled('timetable_entry_id')) {
            return back()->with('error', 'Select a timetable session before recording a substitution.');
        }

        $entry = TimetableEntry::findOrFail($request->timetable_entry_id);
        abort_unless(in_array((int) $entry->program_id, $this->programIds(), true), 403);

        if ($entry->status === 'published') {
            return back()->with('error', 'Published timetable entries require the PMC substitution/change workflow with audit context.');
        }

        TimetableSubstitution::create([
            'timetable_entry_id'    => $request->timetable_entry_id,
            'pmc_generation_item_id' => $entry->pmc_generation_item_id,
            'date'                  => $request->date,
            'action'                => $request->action,
            'substitute_teacher_id' => $request->substitute_teacher_id,
            'reason'                => $request->reason,
            'created_by'            => Auth::id(),
        ]);

        return back()->with('success', 'Substitution recorded.');
    }

    private function createCanonicalSubstitution(Request $request, int $pmcGenerationItemId)
    {
        $item = AcademicPmcTimetableGenerationItem::with(['courseGroup', 'subject', 'teacher.user', 'slot', 'timetableVersion'])
            ->findOrFail($pmcGenerationItemId);

        $programId = (int) ($item->program_id ?: $item->courseGroup?->program_id);
        abort_unless(in_array($programId, $this->programIds(), true), 403);

        if (
            $item->official_status !== 'published'
            || ! $item->timetable_version_id
            || $item->timetableVersion?->status !== 'published'
        ) {
            return back()->with('error', 'Canonical substitutions must target an official published PMC session.');
        }

        if ($request->action === 'substitute') {
            AcademicPmcSubstitutionRecommendation::create([
                'pmc_generation_item_id' => $item->id,
                'timetable_entry_id' => $item->operational_timetable_entry_id,
                'course_group_id' => $item->course_group_id,
                'original_teacher_id' => $item->teacher_id,
                'substitute_teacher_id' => $request->substitute_teacher_id,
                'substitution_date' => $request->date,
                'status' => $request->filled('substitute_teacher_id') ? 'recorded' : 'uncovered',
                'score' => $request->filled('substitute_teacher_id') ? 100 : 0,
                'reasons' => array_values(array_filter([
                    'program_chair_manual_substitution',
                    $request->reason,
                ])),
                'conflict_checks' => [
                    'source' => 'program_chair_substitution_form',
                    'target_day' => $item->day_of_week,
                    'target_slot_id' => $item->timetable_slot_id,
                    'session_type' => $item->session_type,
                    'duration_slots' => $item->duration_slots,
                ],
            ]);

            return back()->with('success', 'Canonical substitution recorded for the official PMC session.');
        }

        $changeType = $request->action === 'cancelled' ? 'cancellation' : 'reschedule';
        app(AcademicPmcTimetableV041Service::class)->requestChange(Auth::user(), [
            'pmc_generation_item_id' => $item->id,
            'timetable_version_id' => $item->timetable_version_id,
            'change_type' => $changeType,
            'reason' => $request->reason ?: 'Program Chair requested ' . str_replace('_', ' ', $changeType) . '.',
            'impact_summary' => [
                'source' => 'program_chair_substitution_form',
                'requested_date' => $request->date,
                'session_label' => $item->courseGroup?->name ?? $item->subject?->name,
            ],
        ]);

        return back()->with('success', 'Canonical timetable change request recorded for the official PMC session.');
    }

    // ── Teacher availability ──────────────────────────────────────────────────
    public function teacherAvailability(Request $request) {
        $currentTerm = Term::latest('start_date')->first();
        $slots = TimetableSlot::where('is_active', true)->orderBy('sort_order')->get();
        $days  = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday'];

        $teachers = Teacher::with('user')->where('status','active')->orderBy('id')->get();

        $selectedTeacher = $request->filled('teacher_id')
            ? Teacher::with('user')->find($request->teacher_id)
            : $teachers->first();

        $availability = TeacherAvailability::where('teacher_id', $selectedTeacher?->id)
            ->when($currentTerm, fn($q) => $q->where('term_id', $currentTerm->id))
            ->get()
            ->keyBy(fn($a) => $a->day_of_week . '-' . $a->timetable_slot_id);

        return view('departmental.program-chair.timetable.availability', compact(
            'teachers','selectedTeacher','slots','days','availability','currentTerm'
        ));
    }

    public function saveAvailability(Request $request) {
        $request->validate([
            'teacher_id'  => 'required|exists:teachers,id',
            'term_id'     => 'required|exists:terms,id',
            'availability'=> 'required|array',
        ]);

        // Delete existing for this teacher+term, then recreate from POST
        TeacherAvailability::where('teacher_id', $request->teacher_id)
            ->where('term_id', $request->term_id)
            ->delete();

        foreach ($request->availability as $key => $value) {
            [$day, $slotId] = explode('-', $key);
            if ($value !== 'available') {
                TeacherAvailability::create([
                    'teacher_id'        => $request->teacher_id,
                    'term_id'           => $request->term_id,
                    'day_of_week'       => $day,
                    'timetable_slot_id' => $slotId,
                    'availability'      => $value,
                ]);
            }
        }

        return back()->with('success', 'Availability saved.');
    }

    // ── Bulk Import from CSV ──────────────────────────────────────────────────
    public function importForm(Request $request) {
        $programIds = $this->programIds();
        $programs   = Program::whereIn('id', $programIds)->orderBy('name')->get();
        $terms      = Term::orderBy('start_date', 'desc')->take(8)->get();
        $batches    = Batch::whereIn('program_id', $programIds)->orderBy('name')->get();

        $selectedProgram = $request->filled('program_id')
            ? Program::find($request->program_id) : $programs->first();

        $selectedTerm = $request->filled('term_id')
            ? Term::find($request->term_id) : Term::latest('start_date')->first();

        $selectedBatch = $request->filled('batch_id')
            ? Batch::find($request->batch_id) : null;

        return view('departmental.program-chair.timetable.import', compact(
            'programs', 'terms', 'batches', 'selectedProgram', 'selectedTerm', 'selectedBatch'
        ));
    }

    public function validateImport(Request $request) {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
            'term_id'    => 'required|exists:terms,id',
            'batch_id'   => 'nullable|exists:batches,id',
            'file'       => 'required|mimes:csv,txt|max:5120',
        ]);

        if ($message = $this->validateProgramTermBatchScope((int) $request->program_id, (int) $request->term_id, $request->filled('batch_id') ? (int) $request->batch_id : null)) {
            return response()->json(['success' => false, 'errors' => [$message]], 422);
        }

        $service = app(TimetableImportService::class);
        $result = $service->validateCSV(
            $request->file('file'),
            $request->program_id,
            $request->term_id,
            $request->batch_id
        );

        return response()->json($result);
    }

    public function doImport(Request $request) {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
            'term_id'    => 'required|exists:terms,id',
            'batch_id'   => 'nullable|exists:batches,id',
            'file'       => 'required|mimes:csv,txt|max:5120',
        ]);

        if ($message = $this->validateProgramTermBatchScope((int) $request->program_id, (int) $request->term_id, $request->filled('batch_id') ? (int) $request->batch_id : null)) {
            return back()->with('error', $message);
        }

        if ($response = $this->blockIfPublished((int) $request->program_id, (int) $request->term_id, $request->filled('batch_id') ? (int) $request->batch_id : null)) {
            return $response;
        }

        $program = Program::findOrFail($request->program_id);
        $term = Term::findOrFail($request->term_id);
        $semester = $this->legacySemesterForTerm($term);
        $course = $this->legacyCourseForProgram($program);

        $service = app(TimetableImportService::class);
        $result = $service->importCSV(
            $request->file('file'),
            $request->program_id,
            $request->term_id,
            $request->batch_id,
            [
                'semester_id' => $semester->id,
                'course_id' => $course->id,
                'created_by' => Auth::id(),
            ]
        );

        if ($result['success']) {
            return back()->with('success', "Imported {$result['imported']} timetable entries.");
        } else {
            return back()->with('error', 'Import failed: ' . implode('; ', array_slice($result['errors'], 0, 3)));
        }
    }

    public function downloadSample(Request $request) {
        $request->validate([
            'batch_id' => 'nullable|exists:batches,id',
        ]);

        $service = app(TimetableImportService::class);
        $csv = $service->getSampleCSV($request->batch_id);

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="timetable_import_sample.csv"');
    }

    // ── Copy Timetable from Previous Term ──────────────────────────────────────
    public function copyForm(Request $request) {
        $programIds = $this->programIds();
        $programs   = Program::whereIn('id', $programIds)->orderBy('name')->get();
        $batches    = Batch::whereIn('program_id', $programIds)->orderBy('name')->get();

        $selectedProgram = $request->filled('program_id')
            ? Program::find($request->program_id) : $programs->first();

        $selectedTargetTerm = $request->filled('target_term_id')
            ? Term::find($request->target_term_id) : Term::latest('start_date')->first();

        $selectedSourceTerm = $request->filled('source_term_id')
            ? Term::find($request->source_term_id) : null;

        $selectedBatch = $request->filled('batch_id')
            ? Batch::find($request->batch_id) : null;

        $service = app(TimetableCopyService::class);
        $availableSourceTerms = $selectedProgram
            ? $service->getAvailableSourceTerms($selectedProgram->id)
            : [];

        $preview = null;
        if ($selectedProgram && $selectedSourceTerm && $selectedTargetTerm) {
            $preview = $service->previewCopy(
                $selectedSourceTerm->id,
                $selectedTargetTerm->id,
                $selectedProgram->id,
                $selectedBatch?->id
            );
        }

        $allTerms = Term::orderBy('start_date', 'desc')->take(12)->get();

        return view('departmental.program-chair.timetable.copy', compact(
            'programs', 'batches', 'allTerms', 'selectedProgram', 'selectedSourceTerm',
            'selectedTargetTerm', 'selectedBatch', 'availableSourceTerms', 'preview'
        ));
    }

    public function previewCopy(Request $request) {
        $request->validate([
            'program_id'       => 'required|exists:programs,id',
            'source_term_id'   => 'required|exists:terms,id',
            'target_term_id'   => 'required|exists:terms,id',
            'batch_id'         => 'nullable|exists:batches,id',
        ]);

        if ($message = $this->validateProgramTermBatchScope((int) $request->program_id, (int) $request->source_term_id, $request->filled('batch_id') ? (int) $request->batch_id : null)) {
            return response()->json(['success' => false, 'errors' => [$message]], 422);
        }

        if ($message = $this->validateProgramTermBatchScope((int) $request->program_id, (int) $request->target_term_id, $request->filled('batch_id') ? (int) $request->batch_id : null)) {
            return response()->json(['success' => false, 'errors' => [$message]], 422);
        }

        $service = app(TimetableCopyService::class);
        $preview = $service->previewCopy(
            $request->source_term_id,
            $request->target_term_id,
            $request->program_id,
            $request->batch_id
        );

        return response()->json($preview);
    }

    public function executeCopy(Request $request) {
        $request->validate([
            'program_id'        => 'required|exists:programs,id',
            'source_term_id'    => 'required|exists:terms,id',
            'target_term_id'    => 'required|exists:terms,id',
            'batch_id'          => 'nullable|exists:batches,id',
            'replace_existing'  => 'sometimes|boolean',
            'reassign_teachers' => 'sometimes|boolean',
            'reassign_classrooms' => 'sometimes|boolean',
        ]);

        if ($message = $this->validateProgramTermBatchScope((int) $request->program_id, (int) $request->source_term_id, $request->filled('batch_id') ? (int) $request->batch_id : null)) {
            return back()->with('error', $message);
        }

        if ($message = $this->validateProgramTermBatchScope((int) $request->program_id, (int) $request->target_term_id, $request->filled('batch_id') ? (int) $request->batch_id : null)) {
            return back()->with('error', $message);
        }

        if ($response = $this->blockIfPublished((int) $request->program_id, (int) $request->target_term_id, $request->filled('batch_id') ? (int) $request->batch_id : null)) {
            return $response;
        }

        $program = Program::findOrFail($request->program_id);
        $targetTerm = Term::findOrFail($request->target_term_id);
        $semester = $this->legacySemesterForTerm($targetTerm);
        $course = $this->legacyCourseForProgram($program);

        $service = app(TimetableCopyService::class);
        $result = $service->executeCopy(
            $request->source_term_id,
            $request->target_term_id,
            $request->program_id,
            $request->batch_id,
            [
                'replace_existing'  => $request->boolean('replace_existing'),
                'reassign_teachers' => $request->boolean('reassign_teachers'),
                'reassign_classrooms' => $request->boolean('reassign_classrooms'),
                'semester_id' => $semester->id,
                'course_id' => $course->id,
                'created_by' => Auth::id(),
            ]
        );

        if ($result['success']) {
            return back()->with('success', $result['message']);
        } else {
            return back()->with('error', $result['message'] . ' ' . implode('; ', array_slice($result['errors'], 0, 2)));
        }
    }

    // ── PDF Export ────────────────────────────────────────────────────────────
    public function exportBatchPdf(Request $request) {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
            'term_id'    => 'required|exists:terms,id',
            'batch_id'   => 'required|exists:batches,id',
        ]);

        if ($message = $this->validateProgramTermBatchScope((int) $request->program_id, (int) $request->term_id, (int) $request->batch_id)) {
            return back()->with('error', $message);
        }

        $batch = Batch::findOrFail($request->batch_id);
        $service = app(TimetablePdfService::class);
        $pdf = $service->generateBatchPdf($request->program_id, $request->term_id, $request->batch_id);

        return $pdf->download('timetable_' . $batch->name . '.pdf');
    }

    public function exportTeacherPdf(Request $request) {
        $request->validate([
            'term_id'    => 'required|exists:terms,id',
            'teacher_id' => 'required|exists:teachers,id',
        ]);

        $teacher = Teacher::with('user')->findOrFail($request->teacher_id);
        $visibleProgramIds = $this->hasAcademicOversight() ? null : $this->programIds();
        abort_if($visibleProgramIds !== null && empty($visibleProgramIds), 403);

        if ($visibleProgramIds !== null) {
            $hasVisibleLegacyEntries = TimetableEntry::where('teacher_id', $teacher->id)
                ->where('term_id', $request->term_id)
                ->whereIn('program_id', $visibleProgramIds)
                ->where('is_active', true)
                ->exists();

            $hasVisibleCanonicalEntries = AcademicPmcTimetableGenerationItem::where('teacher_id', $teacher->id)
                ->where(function ($query) use ($request) {
                    $query->where('term_id', $request->term_id)
                        ->orWhereHas('courseGroup', fn ($group) => $group->where('term_id', $request->term_id));
                })
                ->where(function ($query) use ($visibleProgramIds) {
                    $query->whereIn('program_id', $visibleProgramIds)
                        ->orWhereHas('courseGroup', fn ($group) => $group->whereIn('program_id', $visibleProgramIds));
                })
                ->where('official_status', 'published')
                ->whereNotNull('timetable_version_id')
                ->whereHas('timetableVersion', fn ($version) => $version->where('status', 'published'))
                ->exists();

            abort_unless($hasVisibleLegacyEntries || $hasVisibleCanonicalEntries, 403);
        }

        $service = app(TimetablePdfService::class);
        $pdf = $service->generateTeacherPdf($request->term_id, $request->teacher_id, $visibleProgramIds);

        return $pdf->download('timetable_' . \Illuminate\Support\Str::slug($teacher->user->name) . '.pdf');
    }

    // ── Teacher Workload Warnings ─────────────────────────────────────────────
    public function checkTeacherWorkload(Request $request) {
        $request->validate([
            'teacher_id'       => 'required|exists:teachers,id',
            'term_id'          => 'required|exists:terms,id',
            'timetable_slot_id' => 'required|exists:timetable_slots,id',
        ]);

        $service = app(TeacherWorkloadWarningService::class);
        $warning = $service->getAssignmentWarning(
            $request->teacher_id,
            $request->term_id,
            $request->timetable_slot_id
        );

        return response()->json($warning);
    }

    public function teacherWorkloadList(Request $request) {
        $request->validate([
            'term_id' => 'required|exists:terms,id',
        ]);

        $service = app(TeacherWorkloadWarningService::class);
        $warnings = $service->getTeachersWithWarnings($request->term_id);

        return response()->json([
            'warnings' => $warnings,
            'total' => count($warnings),
            'overloaded' => count(array_filter($warnings, fn($w) => $w['warning_type'] === 'overload')),
            'approaching' => count(array_filter($warnings, fn($w) => $w['warning_type'] === 'approaching')),
        ]);
    }

    public function suggestTeachers(Request $request) {
        $request->validate([
            'term_id'       => 'required|exists:terms,id',
            'teacher_id'    => 'required|exists:teachers,id',
            'department_id' => 'required|exists:departments,id',
        ]);

        $service = app(TeacherWorkloadWarningService::class);
        $suggestions = $service->suggestAlternativeTeachers(
            $request->term_id,
            $request->teacher_id,
            $request->department_id
        );

        return response()->json(['suggestions' => $suggestions]);
    }

    // ── Conflict Prevention Mode ──────────────────────────────────────────────
    public function checkSlotAvailability(Request $request) {
        $request->validate([
            'day_of_week'       => 'required|integer|between:1,6',
            'slot_id'           => 'required|exists:timetable_slots,id',
            'teacher_id'        => 'required|exists:teachers,id',
            'classroom_id'      => 'required|exists:classrooms,id',
            'batch_id'          => 'required|exists:batches,id',
            'term_id'           => 'required|exists:terms,id',
            'course_group_id'   => 'nullable|exists:academic_pmc_course_groups,id',
        ]);

        $service = app(ConflictPreventionService::class);
        $availability = $service->isSlotAvailable(
            $request->day_of_week,
            $request->slot_id,
            $request->teacher_id,
            $request->classroom_id,
            $request->batch_id,
            $request->term_id,
            $request->filled('course_group_id') ? (int) $request->course_group_id : null
        );

        if (!$availability['available']) {
            $suggestions = $service->getSuggestions(
                $request->day_of_week,
                $request->slot_id,
                $request->teacher_id,
                $request->classroom_id,
                $request->batch_id,
                $request->term_id,
                $request->filled('course_group_id') ? (int) $request->course_group_id : null
            );
            $availability['suggestions'] = $suggestions;
        }

        return response()->json($availability);
    }

    public function getAvailableSlots(Request $request) {
        $request->validate([
            'day_of_week'  => 'required|integer|between:1,6',
            'term_id'      => 'required|exists:terms,id',
            'type'         => 'required|in:teacher,classroom,batch',
            'entity_id'    => 'required|integer',
        ]);

        $service = app(ConflictPreventionService::class);

        $slots = match ($request->type) {
            'teacher' => $service->getAvailableTeacherSlots($request->entity_id, $request->day_of_week, $request->term_id),
            'classroom' => $service->getAvailableClassroomSlots($request->entity_id, $request->day_of_week, $request->term_id),
            'batch' => $service->getAvailableBatchSlots($request->entity_id, $request->day_of_week, $request->term_id),
            default => [],
        };

        return response()->json(['slots' => $slots]);
    }

    public function getSuggestions(Request $request) {
        $request->validate([
            'day_of_week'   => 'required|integer|between:1,6',
            'slot_id'       => 'required|exists:timetable_slots,id',
            'teacher_id'    => 'required|exists:teachers,id',
            'classroom_id'  => 'required|exists:classrooms,id',
            'batch_id'      => 'required|exists:batches,id',
            'term_id'       => 'required|exists:terms,id',
            'course_group_id' => 'nullable|exists:academic_pmc_course_groups,id',
        ]);

        $service = app(ConflictPreventionService::class);
        $suggestions = $service->getSuggestions(
            $request->day_of_week,
            $request->slot_id,
            $request->teacher_id,
            $request->classroom_id,
            $request->batch_id,
            $request->term_id,
            $request->filled('course_group_id') ? (int) $request->course_group_id : null
        );

        return response()->json(['suggestions' => $suggestions]);
    }

    // ── Auto-Scheduling Algorithm ─────────────────────────────────────────────
    public function suggestAutoSchedule(Request $request) {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
            'term_id'    => 'required|exists:terms,id',
            'batch_id'   => 'nullable|exists:batches,id',
        ]);

        if ($message = $this->validateAcademicScope($request)) {
            return response()->json(['success' => false, 'errors' => [$message]], 422);
        }

        $service = app(AutoSchedulingService::class);
        $result = $service->suggestSchedule(
            $request->program_id,
            $request->term_id,
            $request->batch_id
        );

        return response()->json($result);
    }

    public function acceptAutoScheduleSuggestions(Request $request) {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
            'term_id'    => 'required|exists:terms,id',
            'suggestions' => 'required|array',
            'suggestions.*.subject_id' => 'required|exists:subjects,id',
            'suggestions.*.batch_id' => 'required|exists:batches,id',
            'suggestions.*.teacher_id' => 'required|exists:teachers,id',
            'suggestions.*.classroom_id' => 'required|exists:classrooms,id',
            'suggestions.*.day_of_week' => 'required|integer|between:1,6',
            'suggestions.*.timetable_slot_id' => 'required|exists:timetable_slots,id',
            'suggestions.*.course_group_id' => 'nullable|exists:academic_pmc_course_groups,id',
            'suggestions.*.group_type' => 'nullable|string|max:80',
            'suggestions.*.duration_slots' => 'nullable|integer|min:1|max:6',
        ]);

        if ($message = $this->validateAcademicScope($request)) {
            return back()->with('error', $message);
        }

        if ($response = $this->blockIfPublished((int) $request->program_id, (int) $request->term_id)) {
            return $response;
        }

        foreach ($request->suggestions as $suggestion) {
            $batch = Batch::find($suggestion['batch_id']);
            $subject = Subject::find($suggestion['subject_id']);
            $courseGroup = isset($suggestion['course_group_id'])
                ? AcademicPmcCourseGroup::find($suggestion['course_group_id'])
                : null;

            if (! $batch || (int) $batch->program_id !== (int) $request->program_id) {
                return back()->with('error', 'Suggested batch does not belong to the selected program.');
            }

            if (! $subject || ($subject->program_id !== null && (int) $subject->program_id !== (int) $request->program_id)) {
                return back()->with('error', 'Suggested subject does not belong to the selected program.');
            }

            if ($courseGroup && (
                (int) $courseGroup->program_id !== (int) $request->program_id
                || (int) $courseGroup->term_id !== (int) $request->term_id
                || (int) $courseGroup->batch_id !== (int) $suggestion['batch_id']
                || (int) $courseGroup->subject_id !== (int) $suggestion['subject_id']
            )) {
                return back()->with('error', 'Suggested course group does not match the selected program, term, batch, and subject.');
            }

            if ($this->hasPublishedVersion((int) $request->program_id, (int) $request->term_id, (int) $suggestion['batch_id'])) {
                return back()->with('error', $this->publishedLockMessage());
            }
        }

        $created = 0;
        $errors = [];
        $canonicalSuggestions = collect($request->suggestions)->filter(fn ($suggestion) => ! empty($suggestion['course_group_id']))->values();

        if ($canonicalSuggestions->isNotEmpty()) {
            $run = AcademicPmcTimetableGenerationRun::create([
                'title' => 'Accepted auto-schedule suggestions',
                'strategy' => 'course_group_auto_schedule',
                'program_id' => $request->program_id,
                'batch_id' => $canonicalSuggestions->pluck('batch_id')->unique()->count() === 1 ? $canonicalSuggestions->first()['batch_id'] : null,
                'term_id' => $request->term_id,
                'created_by' => Auth::id(),
                'status' => 'draft',
                'scheduled_count' => 0,
                'unscheduled_count' => 0,
                'quality_score' => 0,
                'input_summary' => [
                    'source' => 'program_chair_auto_schedule_acceptance',
                    'suggestion_count' => $canonicalSuggestions->count(),
                ],
            ]);

            foreach ($canonicalSuggestions as $index => $suggestion) {
                try {
                    $courseGroup = AcademicPmcCourseGroup::find($suggestion['course_group_id']);
                    AcademicPmcTimetableGenerationItem::create([
                        'generation_run_id' => $run->id,
                        'course_group_id' => $courseGroup?->id,
                        'program_id' => $request->program_id,
                        'batch_id' => $suggestion['batch_id'],
                        'term_id' => $request->term_id,
                        'subject_id' => $suggestion['subject_id'],
                        'session_index' => $index + 1,
                        'session_type' => $this->sessionTypeForCourseGroup($courseGroup, $suggestion),
                        'duration_slots' => $suggestion['duration_slots'] ?? $this->durationForCourseGroup($courseGroup),
                        'teacher_id' => $suggestion['teacher_id'],
                        'classroom_id' => $suggestion['classroom_id'],
                        'day_of_week' => $suggestion['day_of_week'],
                        'timetable_slot_id' => $suggestion['timetable_slot_id'],
                        'status' => 'scheduled',
                        'official_status' => 'draft',
                        'source_type' => 'auto_schedule_acceptance',
                        'confidence' => (int) round((float) ($suggestion['confidence'] ?? 0)),
                        'explanation' => $suggestion['reason'] ?? null,
                        'metadata' => [
                            'accepted_from' => 'program_chair_legacy_auto_schedule',
                            'group_type' => $suggestion['group_type'] ?? $courseGroup?->group_type,
                        ],
                    ]);
                    $created++;
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }

            $run->update(['scheduled_count' => $created]);

            if ($created > 0) {
                return back()->with('success', "Auto-scheduled {$created} canonical PMC sessions. Review and publish through the PMC timetable workflow.");
            }

            return back()->with('error', 'Failed to create auto-schedule. ' . implode('; ', array_slice($errors, 0, 2)));
        }

        return back()->with('error', 'Legacy direct timetable auto-schedule is disabled. Create PMC course groups and generate canonical sessions before publishing.');
    }

    private function sessionTypeForCourseGroup(?AcademicPmcCourseGroup $courseGroup, array $suggestion): string
    {
        if (($suggestion['group_type'] ?? null) === 'lab_group' || $courseGroup?->group_type === 'lab_group') {
            return 'lab';
        }

        return 'lecture';
    }

    private function durationForCourseGroup(?AcademicPmcCourseGroup $courseGroup): int
    {
        if ($courseGroup?->group_type === 'lab_group') {
            return 2;
        }

        return 1;
    }
}
