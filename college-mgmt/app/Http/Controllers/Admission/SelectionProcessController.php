<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ScoringParameter;
use App\Models\SelectionProcessStep;
use App\Models\SelectionSession;
use Illuminate\Http\Request;

class SelectionProcessController extends Controller
{
    // ── Steps ──────────────────────────────────────────────────────────────

    public function steps(Program $program)
    {
        $steps = SelectionProcessStep::where('program_id', $program->id)
            ->orderBy('step_order')
            ->withCount('scoringParameters')
            ->get();
        $programs = Program::where('is_active', true)->orderBy('name')->get();
        return view('admission.selection-process.steps', compact('program', 'programs', 'steps'));
    }

    public function createStep(Program $program)
    {
        $nextOrder = SelectionProcessStep::where('program_id', $program->id)->max('step_order') + 1;
        return view('admission.selection-process.create-step', compact('program', 'nextOrder'));
    }

    public function storeStep(Request $request, Program $program)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'type'         => 'required|in:gd,pi,wat,written_test,aptitude,presentation',
            'step_order'   => 'required|integer|min:1',
            'max_score'    => 'required|integer|min:1',
            'weightage'    => 'required|numeric|min:0|max:100',
            'instructions' => 'nullable|string',
        ]);

        SelectionProcessStep::create(array_merge($validated, [
            'program_id' => $program->id,
            'is_active'  => true,
        ]));

        return redirect()->route('admission.selection-process.steps', $program)
            ->with('success', 'Selection step created successfully.');
    }

    public function editStep(SelectionProcessStep $step)
    {
        $program = $step->program;
        return view('admission.selection-process.edit-step', compact('step', 'program'));
    }

    public function updateStep(Request $request, SelectionProcessStep $step)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'type'         => 'required|in:gd,pi,wat,written_test,aptitude,presentation',
            'step_order'   => 'required|integer|min:1',
            'max_score'    => 'required|integer|min:1',
            'weightage'    => 'required|numeric|min:0|max:100',
            'instructions' => 'nullable|string',
        ]);

        $step->update(array_merge($validated, [
            'is_active' => $request->boolean('is_active', true),
        ]));

        return redirect()->route('admission.selection-process.steps', $step->program)
            ->with('success', 'Step updated successfully.');
    }

    public function destroyStep(SelectionProcessStep $step)
    {
        $hasSessions = SelectionSession::where('selection_process_step_id', $step->id)->exists();
        if ($hasSessions) {
            return back()->with('error', 'Cannot delete: sessions have been conducted for this step.');
        }
        $program = $step->program;
        $step->scoringParameters()->delete();
        $step->delete();
        return redirect()->route('admission.selection-process.steps', $program)
            ->with('success', 'Step deleted.');
    }

    // ── Parameters ─────────────────────────────────────────────────────────

    public function parameters(SelectionProcessStep $step)
    {
        $parameters = $step->scoringParameters()->orderBy('sort_order')->get();
        $program = $step->program;
        return view('admission.selection-process.parameters', compact('step', 'program', 'parameters'));
    }

    public function createParameter(SelectionProcessStep $step)
    {
        $nextOrder = ScoringParameter::where('selection_process_step_id', $step->id)->max('sort_order') + 1;
        $program = $step->program;
        return view('admission.selection-process.create-parameter', compact('step', 'program', 'nextOrder'));
    }

    public function storeParameter(Request $request, SelectionProcessStep $step)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'max_score'   => 'required|integer|min:1',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'required|integer|min:1',
        ]);

        ScoringParameter::create(array_merge($validated, [
            'selection_process_step_id' => $step->id,
        ]));

        return redirect()->route('admission.selection-process.parameters', $step)
            ->with('success', 'Scoring parameter added.');
    }

    public function editParameter(ScoringParameter $parameter)
    {
        $step = $parameter->step;
        $program = $step->program;
        return view('admission.selection-process.edit-parameter', compact('parameter', 'step', 'program'));
    }

    public function updateParameter(Request $request, ScoringParameter $parameter)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'max_score'   => 'required|integer|min:1',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'required|integer|min:1',
        ]);
        $parameter->update($validated);
        return redirect()->route('admission.selection-process.parameters', $parameter->step)
            ->with('success', 'Parameter updated.');
    }

    public function destroyParameter(ScoringParameter $parameter)
    {
        $step = $parameter->step;
        $parameter->delete();
        return redirect()->route('admission.selection-process.parameters', $step)
            ->with('success', 'Parameter deleted.');
    }
}
