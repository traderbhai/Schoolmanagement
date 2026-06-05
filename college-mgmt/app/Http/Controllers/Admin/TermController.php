<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Term, Batch};
use Illuminate\Http\Request;

class TermController extends Controller
{
    public function store(Request $r)
    {
        $r->validate([
            'batch_id'    => 'required|exists:batches,id',
            'term_number' => 'required|integer|min:1',
            'name'        => 'required|string|max:100',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date',
        ]);
        $batch = Batch::findOrFail($r->batch_id);
        Term::create(array_merge($r->all(), ['program_id' => $batch->program_id]));
        return back()->with('success', 'Term added.');
    }

    public function update(Request $r, Term $term)
    {
        $r->validate([
            'name'       => 'required|string|max:100',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
            'is_current' => 'nullable|boolean',
        ]);

        // Only one term can be current per batch
        if ($r->boolean('is_current')) {
            Term::where('batch_id', $term->batch_id)->update(['is_current' => false]);
        }
        $term->update($r->all());
        return back()->with('success', 'Term updated.');
    }

    public function destroy(Term $term)
    {
        $term->delete();
        return back()->with('success', 'Term deleted.');
    }

    public function setCurrent(Term $term)
    {
        Term::where('batch_id', $term->batch_id)->update(['is_current' => false]);
        $term->update(['is_current' => true]);
        return back()->with('success', "'{$term->name}' set as current term.");
    }
}
