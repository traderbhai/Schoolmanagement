<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Program, Batch, AdmissionFormConfig, RequiredDocument, SelectionProcessStep, ScoringParameter, AdmissionFeeInstallment};
use Illuminate\Http\Request;

class AdmissionConfigController extends Controller
{
    // Main hub: show all config sections for a program
    public function index(Program $program)
    {
        $program->load('admissionFormConfig', 'requiredDocuments', 'selectionProcessSteps.scoringParameters', 'admissionFeeInstallments', 'batches');
        return view('admin.admission-config.index', compact('program'));
    }

    // ---- APPLICATION FORM CONFIG ----
    public function editFormConfig(Program $program)
    {
        $config = $program->admissionFormConfig ?? new AdmissionFormConfig([
            'program_id' => $program->id,
            'form_sections' => AdmissionFormConfig::getDefaultSections(),
        ]);
        return view('admin.admission-config.form-config', compact('program', 'config'));
    }

    public function updateFormConfig(Request $r, Program $program)
    {
        $r->validate(['form_sections' => 'required|json']);
        $sections = json_decode($r->form_sections, true);
        AdmissionFormConfig::updateOrCreate(
            ['program_id' => $program->id],
            ['form_sections' => $sections, 'is_active' => true]
        );
        return redirect()->route('admin.admission-config.index', $program)->with('success', 'Application form configuration saved.');
    }

    // ---- REQUIRED DOCUMENTS ----
    public function storeDocument(Request $r, Program $program)
    {
        $r->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string|max:500',
            'is_mandatory' => 'boolean',
            'accepted_formats' => 'nullable|string|max:100',
            'max_size_kb' => 'nullable|integer|min:100|max:10240',
        ]);
        $program->requiredDocuments()->create(array_merge($r->all(), [
            'sort_order' => $program->requiredDocuments()->max('sort_order') + 1,
        ]));
        return back()->with('success', 'Document requirement added.');
    }

    public function updateDocument(Request $r, RequiredDocument $document)
    {
        $r->validate(['name' => 'required|string|max:191', 'is_mandatory' => 'boolean']);
        $document->update($r->all());
        return back()->with('success', 'Document updated.');
    }

    public function destroyDocument(RequiredDocument $document)
    {
        $document->delete();
        return back()->with('success', 'Document requirement removed.');
    }

    public function seedDefaultDocuments(Program $program)
    {
        foreach (RequiredDocument::defaults() as $doc) {
            $program->requiredDocuments()->firstOrCreate(['name' => $doc['name']], $doc);
        }
        return back()->with('success', 'Default document requirements loaded.');
    }

    // ---- SELECTION PROCESS ----
    public function storeStep(Request $r, Program $program)
    {
        $r->validate([
            'name' => 'required|string|max:191',
            'type' => 'required|in:gd,pi,wat,written_test,aptitude,presentation',
            'step_order' => 'required|integer|min:1',
            'max_score' => 'required|integer|min:1|max:1000',
            'weightage' => 'required|numeric|min:1|max:100',
            'instructions' => 'nullable|string|max:1000',
        ]);
        $program->selectionProcessSteps()->create($r->all());
        return back()->with('success', 'Selection step added.');
    }

    public function updateStep(Request $r, SelectionProcessStep $step)
    {
        $r->validate([
            'name' => 'required|string|max:191',
            'max_score' => 'required|integer|min:1|max:1000',
            'weightage' => 'required|numeric|min:1|max:100',
        ]);
        $step->update($r->all());
        return back()->with('success', 'Step updated.');
    }

    public function destroyStep(SelectionProcessStep $step)
    {
        $step->delete();
        return back()->with('success', 'Selection step removed.');
    }

    // ---- SCORING PARAMETERS ----
    public function storeParameter(Request $r, SelectionProcessStep $step)
    {
        $r->validate([
            'name' => 'required|string|max:191',
            'max_score' => 'required|integer|min:1|max:100',
            'description' => 'nullable|string|max:500',
        ]);
        $step->scoringParameters()->create(array_merge($r->all(), [
            'sort_order' => $step->scoringParameters()->max('sort_order') + 1,
        ]));
        return back()->with('success', 'Scoring parameter added.');
    }

    public function destroyParameter(ScoringParameter $parameter)
    {
        $parameter->delete();
        return back()->with('success', 'Parameter removed.');
    }

    // ---- FEE INSTALLMENTS ----
    public function storeFeeInstallment(Request $r, Program $program)
    {
        $r->validate([
            'name' => 'required|string|max:191',
            'amount' => 'required|numeric|min:0',
            'installment_number' => 'required|integer|min:1',
            'due_date' => 'nullable|date',
            'batch_id' => 'nullable|exists:batches,id',
            'description' => 'nullable|string|max:500',
        ]);
        $program->admissionFeeInstallments()->create($r->all());
        return back()->with('success', 'Fee installment added.');
    }

    public function updateFeeInstallment(Request $r, AdmissionFeeInstallment $installment)
    {
        $r->validate(['name' => 'required|string|max:191', 'amount' => 'required|numeric|min:0']);
        $installment->update($r->all());
        return back()->with('success', 'Installment updated.');
    }

    public function destroyFeeInstallment(AdmissionFeeInstallment $installment)
    {
        $installment->delete();
        return back()->with('success', 'Installment removed.');
    }
}
