<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionTag;
use App\Models\AdmissionWorkflowConfig;
use App\Services\DepartmentHierarchyService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowConfigController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function index(Request $request)
    {
        abort_unless($this->hierarchy->canManageDepartmentSettings($request->user(), 'ADM') || $this->hierarchy->hasPermission($request->user(), 'ADM', 'configure_process'), 403);

        $configs = AdmissionWorkflowConfig::orderBy('type')->orderBy('sort_order')->get()->groupBy('type');
        $tags = AdmissionTag::orderBy('name')->get();

        return view('admission.workflow-config.index', compact('configs', 'tags'));
    }

    public function storeConfig(Request $request)
    {
        abort_unless($this->hierarchy->canManageDepartmentSettings($request->user(), 'ADM') || $this->hierarchy->hasPermission($request->user(), 'ADM', 'configure_process'), 403);

        $validated = $request->validate([
            'type' => ['required', 'in:lead_stage,outcome,reason,sla_profile,attention_rule'],
            'key' => ['nullable', 'string', 'max:120'],
            'label' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'config' => ['nullable', 'array'],
        ]);

        $validated['key'] = $validated['key'] ?: Str::slug($validated['label'], '_');
        AdmissionWorkflowConfig::updateOrCreate(
            ['type' => $validated['type'], 'key' => $validated['key']],
            $validated + ['is_active' => $request->boolean('is_active', true)]
        );

        return back()->with('success', 'Workflow config saved.');
    }

    public function storeTag(Request $request)
    {
        abort_unless($this->hierarchy->hasPermission($request->user(), 'ADM', 'assign_work') || $this->hierarchy->hasPermission($request->user(), 'ADM', 'configure_process'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        AdmissionTag::updateOrCreate(
            ['slug' => Str::slug($validated['name'])],
            ['name' => $validated['name'], 'color' => $validated['color'] ?? 'secondary', 'is_active' => true]
        );

        return back()->with('success', 'Tag saved.');
    }
}
