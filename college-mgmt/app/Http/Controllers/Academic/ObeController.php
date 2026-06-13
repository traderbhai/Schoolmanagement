<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\{
    CoAttainment, CoPoMapping, CourseOutcome, ObeSurvey, ObeSurveyResponse,
    PoAttainment, Program, ProgramOutcome, ProgramSpecificOutcome, Subject, Term
};
use App\Services\ObeAttainmentService;
use Illuminate\Http\Request;

class ObeController extends Controller
{
    public function __construct(private ObeAttainmentService $obe) {}

    /* ── COURSE OUTCOMES ──────────────────────────────────────────────────── */

    public function coIndex(Request $request)
    {
        $programs = Program::orderBy('name')->get();
        $terms    = Term::orderBy('name')->get();

        $subjectId = $request->subject_id;
        $subject   = $subjectId ? Subject::with('program')->findOrFail($subjectId) : null;

        $subjects  = collect();
        if ($request->program_id) {
            $subjects = Subject::where('program_id', $request->program_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        $outcomes = $subjectId
            ? CourseOutcome::where('subject_id', $subjectId)->orderBy('code')->get()
            : collect();

        return view('academic.obe.co-index', compact('programs', 'terms', 'subjects', 'subject', 'outcomes'));
    }

    public function coStore(Request $request)
    {
        $data = $request->validate([
            'subject_id'  => 'required|exists:subjects,id',
            'code'        => 'required|string|max:20',
            'description' => 'required|string|max:500',
            'bloom_level' => 'required|in:remember,understand,apply,analyze,evaluate,create',
        ]);

        CourseOutcome::create($data + ['is_active' => true]);
        return back()->with('success', "CO {$data['code']} created.");
    }

    public function coUpdate(Request $request, CourseOutcome $co)
    {
        $data = $request->validate([
            'code'        => 'required|string|max:20',
            'description' => 'required|string|max:500',
            'bloom_level' => 'required|in:remember,understand,apply,analyze,evaluate,create',
            'is_active'   => 'boolean',
        ]);
        $co->update($data);
        return back()->with('success', 'Course outcome updated.');
    }

    public function coDestroy(CourseOutcome $co)
    {
        $co->delete();
        return back()->with('success', 'Course outcome removed.');
    }

    /* ── PROGRAM OUTCOMES ─────────────────────────────────────────────────── */

    public function poIndex(Request $request)
    {
        $programs = Program::orderBy('name')->get();
        $programId = $request->program_id;
        $program   = $programId ? Program::findOrFail($programId) : null;

        $outcomes  = $programId
            ? ProgramOutcome::where('program_id', $programId)->orderBy('code')->get()
            : collect();

        $psos = $programId
            ? ProgramSpecificOutcome::where('program_id', $programId)->orderBy('code')->get()
            : collect();

        return view('academic.obe.po-index', compact('programs', 'program', 'outcomes', 'psos'));
    }

    public function poStore(Request $request)
    {
        $data = $request->validate([
            'program_id'  => 'required|exists:programs,id',
            'code'        => 'required|string|max:20',
            'description' => 'required|string|max:500',
            'category'    => 'required|in:engineering,management,science,commerce,general',
        ]);
        ProgramOutcome::create($data + ['is_active' => true]);
        return back()->with('success', "PO {$data['code']} created.");
    }

    public function poUpdate(Request $request, ProgramOutcome $po)
    {
        $data = $request->validate([
            'code'        => 'required|string|max:20',
            'description' => 'required|string|max:500',
            'category'    => 'required|in:engineering,management,science,commerce,general',
            'is_active'   => 'boolean',
        ]);
        $po->update($data);
        return back()->with('success', 'Program outcome updated.');
    }

    public function poDestroy(ProgramOutcome $po)
    {
        $po->delete();
        return back()->with('success', 'Program outcome removed.');
    }

    public function psoStore(Request $request)
    {
        $data = $request->validate([
            'program_id'  => 'required|exists:programs,id',
            'code'        => 'required|string|max:20',
            'description' => 'required|string|max:500',
        ]);
        ProgramSpecificOutcome::create($data + ['is_active' => true]);
        return back()->with('success', "PSO {$data['code']} created.");
    }

    public function psoDestroy(ProgramSpecificOutcome $pso)
    {
        $pso->delete();
        return back()->with('success', 'PSO removed.');
    }

    /* ── CO-PO MAPPING MATRIX ─────────────────────────────────────────────── */

    public function matrixIndex(Request $request)
    {
        $programs = Program::orderBy('name')->get();
        $programId = $request->program_id;
        $subjectId = $request->subject_id;

        $program  = $programId ? Program::findOrFail($programId) : null;
        $subjects = $programId
            ? Subject::where('program_id', $programId)->where('is_active', true)->orderBy('name')->get()
            : collect();

        $cos = $subjectId
            ? CourseOutcome::where('subject_id', $subjectId)->orderBy('code')->get()
            : collect();

        $pos = $programId
            ? ProgramOutcome::where('program_id', $programId)->where('is_active', true)->orderBy('code')->get()
            : collect();

        $psos = $programId
            ? ProgramSpecificOutcome::where('program_id', $programId)->where('is_active', true)->orderBy('code')->get()
            : collect();

        // Pre-load existing mappings indexed by [co_id][po_id]
        $mappings = collect();
        if ($cos->isNotEmpty() && $pos->isNotEmpty()) {
            $mappings = CoPoMapping::whereIn('course_outcome_id', $cos->pluck('id'))
                ->get()
                ->groupBy('course_outcome_id');
        }

        return view('academic.obe.matrix', compact(
            'programs', 'program', 'subjects', 'cos', 'pos', 'psos', 'mappings',
            'programId', 'subjectId'
        ));
    }

    public function matrixSave(Request $request)
    {
        $request->validate([
            'subject_id'  => 'required|exists:subjects,id',
            'program_id'  => 'required|exists:programs,id',
            'mappings'    => 'nullable|array',
            'mappings.*'  => 'nullable|array',
            'mappings.*.*' => 'nullable|integer|min:0|max:3',
        ]);

        $cos = CourseOutcome::where('subject_id', $request->subject_id)->pluck('id');
        $pos = ProgramOutcome::where('program_id', $request->program_id)->pluck('id');

        // Delete old mappings for these COs
        CoPoMapping::whereIn('course_outcome_id', $cos)->delete();

        $data = $request->input('mappings', []);
        foreach ($data as $coId => $poMap) {
            if (!$cos->contains($coId)) continue;
            foreach ($poMap as $key => $level) {
                $level = (int)$level;
                if ($level < 0 || $level > 3) continue;

                // Key format: "po_123" or "pso_456"
                [$type, $id] = explode('_', $key, 2);
                CoPoMapping::create([
                    'course_outcome_id'           => $coId,
                    'program_outcome_id'           => $type === 'po' ? $id : null,
                    'program_specific_outcome_id'  => $type === 'pso' ? $id : null,
                    'correlation_level'            => $level,
                ]);
            }
        }

        return back()->with('success', 'CO-PO mapping matrix saved.');
    }

    /* ── ATTAINMENT DASHBOARD ─────────────────────────────────────────────── */

    public function attainmentIndex(Request $request)
    {
        $programs  = Program::orderBy('name')->get();
        $terms     = Term::orderBy('name')->get();
        $programId = $request->program_id;
        $termId    = $request->term_id;

        $program  = $programId ? Program::with('programOutcomes')->findOrFail($programId) : null;
        $term     = $termId    ? Term::findOrFail($termId) : null;

        $coAttainments = collect();
        $poAttainments = collect();
        $subjects      = collect();

        if ($programId && $termId) {
            $subjects = Subject::where('program_id', $programId)
                ->whereHas('courseOutcomes')
                ->with(['courseOutcomes.attainments' => fn($q) => $q->where('term_id', $termId)])
                ->orderBy('name')
                ->get();

            $poAttainments = PoAttainment::where('program_id', $programId)
                ->where('term_id', $termId)
                ->with('programOutcome')
                ->orderBy('program_outcome_id')
                ->get();
        }

        return view('academic.obe.attainment', compact(
            'programs', 'terms', 'program', 'term',
            'subjects', 'poAttainments'
        ));
    }

    public function recalculate(Request $request)
    {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
            'term_id'    => 'required|exists:terms,id',
        ]);

        $result = $this->obe->recalculateForTerm($request->program_id, $request->term_id);
        return back()->with('success', "Recalculated: {$result['co_records']} CO records, {$result['po_records']} PO records updated.");
    }

    /* ── OBE SURVEYS (Indirect Assessment) ───────────────────────────────── */

    public function surveyIndex(Request $request)
    {
        $surveys = ObeSurvey::with(['subject', 'term'])
            ->latest()
            ->paginate(20);

        return view('academic.obe.surveys', compact('surveys'));
    }

    public function surveyStore(Request $request)
    {
        $data = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'term_id'    => 'required|exists:terms,id',
            'title'      => 'required|string|max:200',
            'closes_at'  => 'nullable|date|after:today',
        ]);
        ObeSurvey::create($data);
        return back()->with('success', 'Survey created.');
    }

    public function surveyToggle(ObeSurvey $survey)
    {
        $survey->update(['is_published' => !$survey->is_published]);
        $status = $survey->is_published ? 'published' : 'unpublished';
        return back()->with('success', "Survey {$status}.");
    }
}
