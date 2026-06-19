<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\{Batch, Program, AcademicYear, Term};
use App\Services\AcademicMasterDataIntegrityService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class BatchController extends Controller
{
    public function __construct(private AcademicMasterDataIntegrityService $integrity) {}

    public function index()
    {
        $this->authorizeAcademicStructure();

        $batches = Batch::with('program', 'academicYear')->withCount('students', 'terms')->latest()->get();
        return view('admin.batches.index', compact('batches'));
    }

    public function create()
    {
        $this->authorizeAcademicStructure();

        $programs      = Program::where('is_active', true)->get();
        $academicYears = AcademicYear::orderBy('start_date', 'desc')->get();
        return view('admin.batches.create', compact('programs', 'academicYears'));
    }

    public function store(Request $r)
    {
        $this->authorizeAcademicStructure();

        $validated = $r->validate([
            'program_id'      => ['required', Rule::exists('programs', 'id')->where('is_active', true)],
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'name'            => 'required|string|max:191',
            'code'            => 'required|string|max:20|unique:batches,code',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after:start_date',
            'intake_capacity' => 'required|integer|min:1|max:500',
            'status'          => 'required|in:upcoming,active,completed,cancelled',
            'description'     => 'nullable|string',
        ]);
        if ($message = $this->batchAcademicYearBlocker($validated)) {
            return back()->withErrors(['academic_year_id' => $message])->withInput();
        }

        $batch = Batch::create($validated);

        // Auto-generate terms based on program structure
        if ($r->boolean('auto_generate_terms')) {
            $program   = Program::find($validated['program_id']);
            $termLabel = $program->term_type_label;
            for ($i = 1; $i <= $program->total_terms; $i++) {
                Term::create([
                    'batch_id'    => $batch->id,
                    'program_id'  => $batch->program_id,
                    'term_number' => $i,
                    'name'        => $termLabel . ' ' . $this->toRoman($i),
                    'is_current'  => false,
                    'sort_order'  => $i,
                ]);
            }
        }

        return redirect()->route('admin.batches.show', $batch)->with('success', 'Batch created. Terms auto-generated.');
    }

    public function show(Batch $batch)
    {
        $this->authorizeAcademicStructure();

        $batch->load('program', 'academicYear', 'terms', 'students.user');
        return view('admin.batches.show', compact('batch'));
    }

    public function edit(Batch $batch)
    {
        $this->authorizeAcademicStructure();

        $programs      = Program::where('is_active', true)->get();
        $academicYears = AcademicYear::orderBy('start_date', 'desc')->get();
        return view('admin.batches.edit', compact('batch', 'programs', 'academicYears'));
    }

    public function update(Request $r, Batch $batch)
    {
        $this->authorizeAcademicStructure();

        $validated = $r->validate([
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'name'            => 'required|string|max:191',
            'code'            => 'required|string|max:20|unique:batches,code,' . $batch->id,
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after:start_date',
            'intake_capacity' => 'required|integer|min:1|max:500',
            'status'          => 'required|in:upcoming,active,completed,cancelled',
            'description'     => 'nullable|string',
        ]);
        $validated['academic_year_id'] = $r->has('academic_year_id') ? ($validated['academic_year_id'] ?? null) : $batch->academic_year_id;

        $studentCount = $batch->students()->count();
        if ((int) $validated['intake_capacity'] < $studentCount) {
            return back()->withErrors([
                'intake_capacity' => "Batch capacity cannot be reduced below the current enrolled student count ({$studentCount}).",
            ])->withInput();
        }

        if ($validated['status'] === 'cancelled' && $studentCount > 0) {
            return back()->withErrors([
                'status' => 'A batch with enrolled students cannot be cancelled. Close or complete the academic lifecycle instead.',
            ])->withInput();
        }

        if ($this->integrity->hasDependencies('batch', $batch->id) && $this->changesAcademicWindow($batch, $validated)) {
            return back()->withErrors([
                'batch' => 'Batches with students, terms, admissions, timetable, or fee history cannot have academic year or date windows reshaped. Create a new batch/revision instead.',
            ])->withInput();
        }

        if ($message = $this->batchAcademicYearBlocker($validated)) {
            return back()->withErrors(['academic_year_id' => $message])->withInput();
        }

        $batch->update($validated);
        return redirect()->route('admin.batches.show', $batch)->with('success', 'Batch updated.');
    }

    public function destroy(Batch $batch)
    {
        $this->authorizeAcademicStructure();

        $dependencies = $this->integrity->dependencyLabels('batch', $batch->id);

        if ($dependencies !== []) {
            return back()->with('error', $this->integrity->message('batch', $dependencies));
        }

        $batch->delete();
        return redirect()->route('admin.batches.index')->with('success', 'Batch deleted.');
    }

    private function toRoman(int $n): string
    {
        return ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$n] ?? (string)$n;
    }

    private function batchAcademicYearBlocker(array $data): ?string
    {
        if (empty($data['academic_year_id'])) {
            return null;
        }

        $academicYear = AcademicYear::find($data['academic_year_id']);
        if (! $academicYear) {
            return null;
        }

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->startOfDay();

        if ($startDate->lt($academicYear->start_date->copy()->startOfDay()) || $endDate->gt($academicYear->end_date->copy()->startOfDay())) {
            return 'Batch dates must fall within the selected academic year date range.';
        }

        return null;
    }

    private function changesAcademicWindow(Batch $batch, array $data): bool
    {
        return (int) ($batch->academic_year_id ?? 0) !== (int) ($data['academic_year_id'] ?? 0)
            || $batch->start_date->toDateString() !== (string) $data['start_date']
            || $batch->end_date->toDateString() !== (string) $data['end_date'];
    }

    private function authorizeAcademicStructure(): void
    {
        abort_unless(auth()->user() && AccessControl::canManageAcademicStructure(auth()->user()), 403);
    }
}
