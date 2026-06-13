<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionProcessTemplate;
use App\Models\Program;
use Illuminate\Http\Request;

class ProcessTemplateController extends Controller
{
    public function index()
    {
        $programs = Program::where('is_active', true)->orderBy('name')->get();
        $templates = AdmissionProcessTemplate::with(['program', 'batch', 'stages'])->latest()->paginate(20);

        return view('admission.process-templates', compact('programs', 'templates'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'name' => ['required', 'string', 'max:255'],
            'offer_validity_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'waitlist_rule' => ['nullable', 'string', 'max:255'],
        ]);

        $template = AdmissionProcessTemplate::create([
            'program_id' => $validated['program_id'],
            'batch_id' => $validated['batch_id'] ?? null,
            'name' => $validated['name'],
            'is_active' => true,
            'config' => [
                'offer_validity_days' => $validated['offer_validity_days'] ?? 15,
                'waitlist_rule' => $validated['waitlist_rule'] ?? null,
            ],
        ]);

        foreach (['application' => 'Application', 'documents' => 'Documents', 'selection' => 'Selection', 'offer' => 'Offer', 'enrollment' => 'Enrollment'] as $key => $name) {
            $template->stages()->create([
                'name' => $name,
                'stage_key' => $key,
                'sequence' => $template->stages()->count() + 1,
                'is_required' => true,
            ]);
        }

        return back()->with('success', 'Admission process template created.');
    }

    public function storeStage(Request $request, AdmissionProcessTemplate $template)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'stage_key' => ['required', 'string', 'max:80'],
            'sequence' => ['required', 'integer', 'min:1'],
            'is_required' => ['nullable', 'boolean'],
            'sla_hours' => ['nullable', 'integer', 'min:1'],
        ]);

        $template->stages()->updateOrCreate(
            ['stage_key' => $validated['stage_key']],
            [
                'name' => $validated['name'],
                'sequence' => $validated['sequence'],
                'is_required' => $request->boolean('is_required', true),
                'sla_hours' => $validated['sla_hours'] ?? null,
            ]
        );

        return back()->with('success', 'Process stage saved.');
    }
}
