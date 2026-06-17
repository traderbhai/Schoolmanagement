<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Program, Batch, AdmissionFormConfig, RequiredDocument, SelectionProcessStep, ScoringParameter, AdmissionFeeInstallment};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $data = $r->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string|max:500',
            'is_mandatory' => 'boolean',
            'accepted_formats' => 'nullable|string|max:100',
            'max_size_kb' => 'nullable|integer|min:100|max:10240',
        ]);
        $program->requiredDocuments()->create(array_merge($data, [
            'sort_order' => $program->requiredDocuments()->max('sort_order') + 1,
        ]));
        return back()->with('success', 'Document requirement added.');
    }

    public function updateDocument(Request $r, RequiredDocument $document)
    {
        $data = $r->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string|max:500',
            'is_mandatory' => 'boolean',
            'accepted_formats' => 'nullable|string|max:100',
            'max_size_kb' => 'nullable|integer|min:100|max:10240',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($document->applicantDocuments()->exists() && $this->changesRequiredDocumentContract($document, $data)) {
            throw ValidationException::withMessages([
                'required_document' => 'This document requirement is already linked to applicant uploads and cannot be changed or deactivated. Add a new requirement for future applicants instead.',
            ]);
        }

        $document->update($data);
        return back()->with('success', 'Document updated.');
    }

    public function destroyDocument(RequiredDocument $document)
    {
        if ($document->applicantDocuments()->exists()) {
            return back()->with('error', 'This document requirement is linked to applicant uploads and cannot be deleted.');
        }

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
        $data = $r->validate([
            'name' => 'required|string|max:191',
            'type' => 'required|in:gd,pi,wat,written_test,aptitude,presentation',
            'step_order' => 'required|integer|min:1',
            'max_score' => 'required|integer|min:1|max:1000',
            'weightage' => 'required|numeric|min:1|max:100',
            'instructions' => 'nullable|string|max:1000',
        ]);
        $program->selectionProcessSteps()->create($data);
        return back()->with('success', 'Selection step added.');
    }

    public function updateStep(Request $r, SelectionProcessStep $step)
    {
        $data = $r->validate([
            'name' => 'required|string|max:191',
            'type' => 'nullable|in:gd,pi,wat,written_test,aptitude,presentation',
            'step_order' => 'nullable|integer|min:1',
            'max_score' => 'required|integer|min:1|max:1000',
            'weightage' => 'required|numeric|min:1|max:100',
            'instructions' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        if ($this->selectionStepHasActivity($step) && $this->changesSelectionStepContract($step, $data)) {
            throw ValidationException::withMessages([
                'selection_step' => 'This selection step already has sessions or scores and cannot be restructured, reordered, rescored, or deactivated.',
            ]);
        }

        $step->update($data);
        return back()->with('success', 'Step updated.');
    }

    public function destroyStep(SelectionProcessStep $step)
    {
        if ($this->selectionStepHasActivity($step)) {
            return back()->with('error', 'This selection step has sessions or scores and cannot be deleted.');
        }

        $step->delete();
        return back()->with('success', 'Selection step removed.');
    }

    // ---- SCORING PARAMETERS ----
    public function storeParameter(Request $r, SelectionProcessStep $step)
    {
        $data = $r->validate([
            'name' => 'required|string|max:191',
            'max_score' => 'required|integer|min:1|max:100',
            'description' => 'nullable|string|max:500',
        ]);
        $step->scoringParameters()->create(array_merge($data, [
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
        $data = $r->validate([
            'name' => 'required|string|max:191',
            'amount' => 'required|numeric|min:0',
            'installment_number' => 'required|integer|min:1',
            'due_date' => 'nullable|date',
            'batch_id' => 'nullable|exists:batches,id',
            'description' => 'nullable|string|max:500',
        ]);
        $program->admissionFeeInstallments()->create($data);
        return back()->with('success', 'Fee installment added.');
    }

    public function updateFeeInstallment(Request $r, AdmissionFeeInstallment $installment)
    {
        $data = $r->validate([
            'name' => 'required|string|max:191',
            'amount' => 'required|numeric|min:0',
            'installment_number' => 'nullable|integer|min:1',
            'due_date' => 'nullable|date',
            'batch_id' => 'nullable|exists:batches,id',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        if ($installment->payments()->exists() && $this->changesAdmissionInstallmentContract($installment, $data)) {
            throw ValidationException::withMessages([
                'admission_fee_installment' => 'This installment is linked to admission payments and cannot be financially changed or deactivated.',
            ]);
        }

        $installment->update($data);
        return back()->with('success', 'Installment updated.');
    }

    public function destroyFeeInstallment(AdmissionFeeInstallment $installment)
    {
        if ($installment->payments()->exists()) {
            return back()->with('error', 'This installment is linked to admission payments and cannot be deleted.');
        }

        $installment->delete();
        return back()->with('success', 'Installment removed.');
    }

    private function changesRequiredDocumentContract(RequiredDocument $document, array $data): bool
    {
        return (string) ($data['name'] ?? $document->name) !== (string) $document->name
            || (bool) ($data['is_mandatory'] ?? $document->is_mandatory) !== (bool) $document->is_mandatory
            || (string) ($data['accepted_formats'] ?? $document->accepted_formats) !== (string) $document->accepted_formats
            || (int) ($data['max_size_kb'] ?? $document->max_size_kb ?? 0) !== (int) ($document->max_size_kb ?? 0)
            || (array_key_exists('is_active', $data) && (bool) $data['is_active'] !== (bool) $document->is_active);
    }

    private function selectionStepHasActivity(SelectionProcessStep $step): bool
    {
        return $step->sessions()->exists() || $step->scores()->exists();
    }

    private function changesSelectionStepContract(SelectionProcessStep $step, array $data): bool
    {
        return (string) ($data['type'] ?? $step->type) !== (string) $step->type
            || (int) ($data['step_order'] ?? $step->step_order) !== (int) $step->step_order
            || (int) ($data['max_score'] ?? $step->max_score) !== (int) $step->max_score
            || number_format((float) ($data['weightage'] ?? $step->weightage), 2, '.', '') !== number_format((float) $step->weightage, 2, '.', '')
            || (array_key_exists('is_active', $data) && (bool) $data['is_active'] !== (bool) $step->is_active);
    }

    private function changesAdmissionInstallmentContract(AdmissionFeeInstallment $installment, array $data): bool
    {
        return number_format((float) ($data['amount'] ?? $installment->amount), 2, '.', '') !== number_format((float) $installment->amount, 2, '.', '')
            || (int) ($data['installment_number'] ?? $installment->installment_number) !== (int) $installment->installment_number
            || (int) ($data['batch_id'] ?? $installment->batch_id ?? 0) !== (int) ($installment->batch_id ?? 0)
            || (array_key_exists('is_active', $data) && (bool) $data['is_active'] !== (bool) $installment->is_active);
    }
}
