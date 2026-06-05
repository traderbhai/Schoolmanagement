<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Batch, Program, AcademicYear, Term};
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function index()
    {
        $batches = Batch::with('program', 'academicYear')->withCount('students', 'terms')->latest()->get();
        return view('admin.batches.index', compact('batches'));
    }

    public function create()
    {
        $programs      = Program::where('is_active', true)->get();
        $academicYears = AcademicYear::orderBy('start_date', 'desc')->get();
        return view('admin.batches.create', compact('programs', 'academicYears'));
    }

    public function store(Request $r)
    {
        $r->validate([
            'program_id'      => 'required|exists:programs,id',
            'name'            => 'required|string|max:191',
            'code'            => 'required|string|max:20|unique:batches,code',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after:start_date',
            'intake_capacity' => 'required|integer|min:1|max:500',
            'status'          => 'required|in:upcoming,active,completed,cancelled',
        ]);

        $batch = Batch::create($r->all());

        // Auto-generate terms based on program structure
        if ($r->boolean('auto_generate_terms')) {
            $program   = Program::find($r->program_id);
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
        $batch->load('program', 'academicYear', 'terms', 'students.user');
        return view('admin.batches.show', compact('batch'));
    }

    public function edit(Batch $batch)
    {
        $programs      = Program::where('is_active', true)->get();
        $academicYears = AcademicYear::orderBy('start_date', 'desc')->get();
        return view('admin.batches.edit', compact('batch', 'programs', 'academicYears'));
    }

    public function update(Request $r, Batch $batch)
    {
        $r->validate([
            'name'            => 'required|string|max:191',
            'code'            => 'required|string|max:20|unique:batches,code,' . $batch->id,
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after:start_date',
            'intake_capacity' => 'required|integer|min:1|max:500',
            'status'          => 'required|in:upcoming,active,completed,cancelled',
        ]);
        $batch->update($r->all());
        return redirect()->route('admin.batches.show', $batch)->with('success', 'Batch updated.');
    }

    public function destroy(Batch $batch)
    {
        if ($batch->students()->count() > 0) {
            return back()->with('error', 'Cannot delete batch with enrolled students.');
        }
        $batch->delete();
        return redirect()->route('admin.batches.index')->with('success', 'Batch deleted.');
    }

    private function toRoman(int $n): string
    {
        return ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$n] ?? (string)$n;
    }
}
