<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\{Term, Batch};
use App\Services\AcademicMasterDataIntegrityService;
use Illuminate\Http\Request;

class TermController extends Controller
{
    public function __construct(private AcademicMasterDataIntegrityService $integrity) {}

    public function store(Request $r)
    {
        $this->authorizeAcademicStructure();

        $r->validate([
            'batch_id'    => 'required|exists:batches,id',
            'term_number' => 'required|integer|min:1',
            'name'        => 'required|string|max:100',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date',
        ]);
        $batch = Batch::with('program')->findOrFail($r->batch_id);
        $termNumber = (int) $r->integer('term_number');

        if (! $batch->program || ! $batch->program->is_active) {
            return back()
                ->withErrors(['batch_id' => 'Terms can be created only for batches under an active program.'])
                ->withInput();
        }

        if (! in_array($batch->status, ['upcoming', 'active'], true)) {
            return back()
                ->withErrors(['batch_id' => 'Terms can be created only for upcoming or active batches.'])
                ->withInput();
        }

        if ($termNumber > (int) $batch->program->total_terms) {
            return back()
                ->withErrors(['term_number' => 'Term number cannot exceed the program configured total terms.'])
                ->withInput();
        }

        if (Term::where('batch_id', $batch->id)->where('term_number', $termNumber)->exists()) {
            return back()
                ->withErrors(['term_number' => 'This batch already has a term with the selected term number.'])
                ->withInput();
        }

        Term::create(array_merge($r->all(), ['program_id' => $batch->program_id]));
        return back()->with('success', 'Term added.');
    }

    public function update(Request $r, Term $term)
    {
        $this->authorizeAcademicStructure();

        $data = $r->validate([
            'name'       => 'required|string|max:100',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
            'is_current' => 'nullable|boolean',
        ]);

        if ($this->integrity->hasDependencies('term', $term->id) && $this->changesDateWindow($term, $data)) {
            return back()
                ->withErrors(['term' => 'Terms with academic history cannot have their start or end dates changed. Create a new term/revision plan instead of reshaping history.'])
                ->withInput();
        }

        // Only one term can be current per batch
        if ($r->boolean('is_current')) {
            Term::where('batch_id', $term->batch_id)->update(['is_current' => false]);
            $data['is_current'] = true;
        } else {
            unset($data['is_current']);
        }

        $term->update($data);
        return back()->with('success', 'Term updated.');
    }

    public function destroy(Term $term)
    {
        $this->authorizeAcademicStructure();

        $dependencies = $this->integrity->dependencyLabels('term', $term->id);

        if ($dependencies !== []) {
            return back()->with('error', $this->integrity->message('term', $dependencies));
        }

        $term->delete();
        return back()->with('success', 'Term deleted.');
    }

    public function setCurrent(Term $term)
    {
        $this->authorizeAcademicStructure();

        $term->loadMissing('batch.program');
        if (! $term->batch || ! $term->batch->program || ! $term->batch->program->is_active) {
            return back()->withErrors([
                'term' => 'Only terms under an active program can be set as current.',
            ]);
        }

        if (! in_array($term->batch->status, ['upcoming', 'active'], true)) {
            return back()->withErrors([
                'term' => 'Only terms in upcoming or active batches can be set as current.',
            ]);
        }

        Term::where('batch_id', $term->batch_id)->update(['is_current' => false]);
        $term->update(['is_current' => true]);
        return back()->with('success', "'{$term->name}' set as current term.");
    }

    private function changesDateWindow(Term $term, array $data): bool
    {
        foreach (['start_date', 'end_date'] as $field) {
            $current = $term->{$field}?->toDateString();
            $incoming = $data[$field] ?? null;

            if ($current !== $incoming) {
                return true;
            }
        }

        return false;
    }

    private function authorizeAcademicStructure(): void
    {
        abort_unless(auth()->user() && AccessControl::canManageAcademicStructure(auth()->user()), 403);
    }
}
