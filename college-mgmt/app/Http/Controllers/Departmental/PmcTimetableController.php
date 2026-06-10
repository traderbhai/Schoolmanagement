<?php
namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{Program, Term, Batch, TimetableEntry, TimetableSlot, TimetableVersion,
                TimetableSubstitution, TeacherAvailability, Subject, Teacher, Classroom,
                RoleProgramAssignment};
use App\Services\{TimetableConflictService, TimetableImportService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PmcTimetableController extends Controller {

    private function programIds(): array {
        $ids = RoleProgramAssignment::where('user_id', Auth::id())
            ->where('is_active', true)->pluck('program_id')->toArray();
        return $ids ?: Program::where('is_active', true)->pluck('id')->toArray();
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
}
