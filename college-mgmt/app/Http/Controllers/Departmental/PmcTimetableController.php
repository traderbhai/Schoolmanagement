<?php
namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{AcademicYear, Course, Program, Term, Batch, TimetableEntry, TimetableSlot, TimetableVersion,
                TimetableSubstitution, TeacherAvailability, Subject, Teacher, Classroom,
                RoleProgramAssignment, Semester};
use App\Services\{TimetableConflictService, TimetableImportService, TimetableCopyService, TimetablePdfService, TeacherWorkloadWarningService, ConflictPreventionService, AutoSchedulingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PmcTimetableController extends Controller {

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
            return back()->with('error', 'Published timetable history is locked on legacy Program Chair routes. Use PMC timetable revision/version workflow for changes.');
        }

        return null;
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

        $slots = TimetableSlot::where('is_active', true)->orderBy('sort_order')->get();
        $days  = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday'];

        // Entries for this program-term-batch
        $entries = TimetableEntry::where('term_id', $selectedTerm?->id)
            ->when($selectedProgram, fn($q) => $q->where('program_id', $selectedProgram->id))
            ->when($selectedBatch,   fn($q) => $q->where('batch_id', $selectedBatch->id))
            ->where('is_active', true)
            ->with(['subject','teacher.user','classroom','slot','batch'])
            ->get()
            ->keyBy(fn($e) => $e->day_of_week . '-' . $e->timetable_slot_id);

        // Subjects in this program-term
        $programSubjects = \App\Models\ProgramSubject::where('program_id', $selectedProgram?->id ?? 0)
            ->when($selectedTerm, fn($q) => $q->where('term_id', $selectedTerm->id))
            ->with('subject')
            ->get();

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
            'slots','days','entries','programSubjects','teachers','classrooms','availability','version'
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
        ]);

        if ($message = $this->validateAcademicScope($request)) {
            return response()->json(['message' => $message], 422);
        }

        if ($this->hasPublishedVersion((int) $request->program_id, (int) $request->term_id, $request->filled('batch_id') ? (int) $request->batch_id : null)) {
            return response()->json(['message' => 'Published timetable history is locked on legacy Program Chair routes. Use PMC timetable revision/version workflow for changes.'], 423);
        }

        $program = Program::findOrFail($request->program_id);
        $term = Term::findOrFail($request->term_id);
        $semester = $this->legacySemesterForTerm($term);
        $course = $this->legacyCourseForProgram($program);

        // Conflict check
        if ($request->filled('subject_id')) {
            $conflicts = app(TimetableConflictService::class)->check([
                'teacher_id'        => $request->teacher_id,
                'classroom_id'      => $request->classroom_id,
                'batch_id'          => $request->batch_id,
                'day_of_week'       => $request->day_of_week,
                'timetable_slot_id' => $request->timetable_slot_id,
                'term_id'           => $request->term_id,
            ]);

            if ($conflicts) {
                return response()->json(['conflicts' => $conflicts], 422);
            }
        }

        // Clear existing entry for this slot
        TimetableEntry::where([
            'program_id'        => $request->program_id,
            'term_id'           => $request->term_id,
            'batch_id'          => $request->batch_id,
            'day_of_week'       => $request->day_of_week,
            'timetable_slot_id' => $request->timetable_slot_id,
        ])->delete();

        // Create new entry if subject is set
        if ($request->filled('subject_id')) {
            TimetableEntry::create([
                'semester_id'       => $semester->id,
                'course_id'         => $course->id,
                'program_id'        => $request->program_id,
                'term_id'           => $request->term_id,
                'batch_id'          => $request->batch_id,
                'subject_id'        => $request->subject_id,
                'teacher_id'        => $request->teacher_id,
                'classroom_id'      => $request->classroom_id,
                'day_of_week'       => $request->day_of_week,
                'timetable_slot_id' => $request->timetable_slot_id,
                'is_active'         => true,
                'status'            => 'draft',
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

        // Mark entries as published
        TimetableEntry::where([
            'program_id' => $request->program_id,
            'term_id'    => $request->term_id,
            'batch_id'   => $request->batch_id,
        ])->update(['status' => 'published', 'timetable_version_id' => $version->id]);

        return back()->with('success', "Timetable published as version {$version->version_number}.");
    }

    // ── Conflict checker (AJAX) ───────────────────────────────────────────────
    public function checkConflict(Request $request) {
        $conflicts = app(TimetableConflictService::class)->check($request->all());
        return response()->json(['conflicts' => $conflicts]);
    }

    // ── Substitutions ─────────────────────────────────────────────────────────
    public function substitutions(Request $request) {
        $programIds = $this->programIds();
        $currentTerm = Term::latest('start_date')->first();

        $entries = TimetableEntry::whereIn('program_id', $programIds)
            ->where('term_id', $currentTerm?->id)
            ->where('is_active', true)
            ->with(['subject','teacher.user','slot','batch'])
            ->get();

        $recent = TimetableSubstitution::whereHas('entry', fn($q) => $q->whereIn('program_id', $programIds))
            ->with(['entry.subject','entry.batch','substitute.user','creator'])
            ->orderByDesc('date')
            ->take(30)
            ->get();

        $teachers = Teacher::with('user')->where('status','active')->get();

        return view('departmental.program-chair.timetable.substitutions', compact(
            'entries', 'recent', 'teachers', 'currentTerm'
        ));
    }

    public function createSubstitution(Request $request) {
        $request->validate([
            'timetable_entry_id'    => 'required|exists:timetable_entries,id',
            'date'                  => 'required|date',
            'action'                => 'required|in:substitute,cancelled,rescheduled',
            'substitute_teacher_id' => 'nullable|exists:teachers,id',
            'reason'                => 'nullable|string|max:300',
        ]);

        $entry = TimetableEntry::findOrFail($request->timetable_entry_id);
        abort_unless(in_array((int) $entry->program_id, $this->programIds(), true), 403);

        if ($entry->status === 'published') {
            return back()->with('error', 'Published timetable entries require the PMC substitution/change workflow with audit context.');
        }

        TimetableSubstitution::create([
            'timetable_entry_id'    => $request->timetable_entry_id,
            'date'                  => $request->date,
            'action'                => $request->action,
            'substitute_teacher_id' => $request->substitute_teacher_id,
            'reason'                => $request->reason,
            'created_by'            => Auth::id(),
        ]);

        return back()->with('success', 'Substitution recorded.');
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

        if ($message = $this->validateProgramTermBatchScope((int) $request->program_id, (int) $request->target_term_id, $request->filled('batch_id') ? (int) $request->batch_id : null)) {
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

        if ($message = $this->validateProgramTermBatchScope((int) $request->program_id, (int) $request->target_term_id, $request->filled('batch_id') ? (int) $request->batch_id : null)) {
            return back()->with('error', $message);
        }

        if ($response = $this->blockIfPublished((int) $request->program_id, (int) $request->term_id, $request->filled('batch_id') ? (int) $request->batch_id : null)) {
            return $response;
        }

        $service = app(TimetableImportService::class);
        $result = $service->importCSV(
            $request->file('file'),
            $request->program_id,
            $request->term_id,
            $request->batch_id
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

        if ($message = $this->validateAcademicScope($request)) {
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

        if ($message = $this->validateAcademicScope($request)) {
            return back()->with('error', $message);
        }

        if ($response = $this->blockIfPublished((int) $request->program_id, (int) $request->target_term_id, $request->filled('batch_id') ? (int) $request->batch_id : null)) {
            return $response;
        }

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

        $batch = Batch::find($request->batch_id);
        $service = app(TimetablePdfService::class);
        $pdf = $service->generateBatchPdf($request->program_id, $request->term_id, $request->batch_id);

        return $pdf->download('timetable_' . $batch->name . '.pdf');
    }

    public function exportTeacherPdf(Request $request) {
        $request->validate([
            'term_id'    => 'required|exists:terms,id',
            'teacher_id' => 'required|exists:teachers,id',
        ]);

        $teacher = Teacher::with('user')->find($request->teacher_id);
        $service = app(TimetablePdfService::class);
        $pdf = $service->generateTeacherPdf($request->term_id, $request->teacher_id);

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
        ]);

        $service = app(ConflictPreventionService::class);
        $availability = $service->isSlotAvailable(
            $request->day_of_week,
            $request->slot_id,
            $request->teacher_id,
            $request->classroom_id,
            $request->batch_id,
            $request->term_id
        );

        if (!$availability['available']) {
            $suggestions = $service->getSuggestions(
                $request->day_of_week,
                $request->slot_id,
                $request->teacher_id,
                $request->classroom_id,
                $request->batch_id,
                $request->term_id
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
        ]);

        $service = app(ConflictPreventionService::class);
        $suggestions = $service->getSuggestions(
            $request->day_of_week,
            $request->slot_id,
            $request->teacher_id,
            $request->classroom_id,
            $request->batch_id,
            $request->term_id
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

            if (! $batch || (int) $batch->program_id !== (int) $request->program_id) {
                return back()->with('error', 'Suggested batch does not belong to the selected program.');
            }

            if (! $subject || ($subject->program_id !== null && (int) $subject->program_id !== (int) $request->program_id)) {
                return back()->with('error', 'Suggested subject does not belong to the selected program.');
            }
        }

        $created = 0;
        $errors = [];

        foreach ($request->suggestions as $suggestion) {
            try {
                TimetableEntry::create([
                    'program_id' => $request->program_id,
                    'term_id' => $request->term_id,
                    'batch_id' => $suggestion['batch_id'],
                    'subject_id' => $suggestion['subject_id'],
                    'teacher_id' => $suggestion['teacher_id'],
                    'classroom_id' => $suggestion['classroom_id'],
                    'day_of_week' => $suggestion['day_of_week'],
                    'timetable_slot_id' => $suggestion['timetable_slot_id'],
                    'is_active' => true,
                    'status' => 'draft',
                ]);
                $created++;
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($created > 0) {
            return back()->with('success', "Auto-scheduled {$created} entries. Review and adjust as needed.");
        } else {
            return back()->with('error', 'Failed to create auto-schedule. ' . implode('; ', array_slice($errors, 0, 2)));
        }
    }
}
