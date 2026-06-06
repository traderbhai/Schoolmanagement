<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionFeeInstallment;
use App\Models\AdmissionPayment;
use App\Models\Batch;
use App\Models\Program;
use Illuminate\Http\Request;

class FeeInstallmentController extends Controller
{
    public function index(Program $program)
    {
        $batches = Batch::where('program_id', $program->id)->orderByDesc('start_date')->get();
        $selectedBatchId = request('batch_id');
        $query = AdmissionFeeInstallment::where('program_id', $program->id)->orderBy('installment_number');
        if ($selectedBatchId) {
            $query->where('batch_id', $selectedBatchId);
        }
        $installments = $query->get();
        $programs = Program::where('is_active', true)->orderBy('name')->get();
        return view('admission.fee-installments.index', compact('program', 'programs', 'batches', 'installments', 'selectedBatchId'));
    }

    public function create(Program $program)
    {
        $batches = Batch::where('program_id', $program->id)->orderByDesc('start_date')->get();
        $nextNumber = AdmissionFeeInstallment::where('program_id', $program->id)->max('installment_number') + 1;
        return view('admission.fee-installments.create', compact('program', 'batches', 'nextNumber'));
    }

    public function store(Request $request, Program $program)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'amount'              => 'required|numeric|min:1',
            'installment_number'  => 'required|integer|min:1',
            'batch_id'            => 'nullable|exists:batches,id',
            'due_date'            => 'nullable|date',
            'description'         => 'nullable|string|max:500',
            'is_active'           => 'boolean',
        ]);

        AdmissionFeeInstallment::create(array_merge($validated, [
            'program_id' => $program->id,
            'is_active'  => $request->boolean('is_active', true),
        ]));

        return redirect()->route('admission.fee-installments.index', $program)
            ->with('success', 'Fee installment created successfully.');
    }

    public function edit(AdmissionFeeInstallment $feeInstallment)
    {
        $program = $feeInstallment->program;
        $batches = Batch::where('program_id', $program->id)->orderByDesc('start_date')->get();
        return view('admission.fee-installments.edit', compact('feeInstallment', 'program', 'batches'));
    }

    public function update(Request $request, AdmissionFeeInstallment $feeInstallment)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'amount'              => 'required|numeric|min:1',
            'installment_number'  => 'required|integer|min:1',
            'batch_id'            => 'nullable|exists:batches,id',
            'due_date'            => 'nullable|date',
            'description'         => 'nullable|string|max:500',
        ]);

        $feeInstallment->update(array_merge($validated, [
            'is_active' => $request->boolean('is_active', true),
        ]));

        return redirect()->route('admission.fee-installments.index', $feeInstallment->program)
            ->with('success', 'Fee installment updated successfully.');
    }

    public function destroy(AdmissionFeeInstallment $feeInstallment)
    {
        $hasPayments = AdmissionPayment::where('admission_fee_installment_id', $feeInstallment->id)->exists();
        if ($hasPayments) {
            return back()->with('error', 'Cannot delete: payments exist for this installment.');
        }
        $program = $feeInstallment->program;
        $feeInstallment->delete();
        return redirect()->route('admission.fee-installments.index', $program)
            ->with('success', 'Fee installment deleted.');
    }

    public function duplicateForm(Program $program)
    {
        $batches = Batch::where('program_id', $program->id)->orderByDesc('start_date')->get();
        return view('admission.fee-installments.duplicate', compact('program', 'batches'));
    }

    public function duplicate(Request $request, Program $program)
    {
        $request->validate([
            'from_batch_id' => 'required|exists:batches,id',
            'to_batch_id'   => 'required|exists:batches,id|different:from_batch_id',
            'days_offset'   => 'nullable|integer',
        ]);

        $source = AdmissionFeeInstallment::where('program_id', $program->id)
            ->where('batch_id', $request->from_batch_id)
            ->get();

        if ($source->isEmpty()) {
            return back()->with('error', 'No installments found for the selected source batch.');
        }

        $offset = (int) ($request->days_offset ?? 0);
        $count = 0;
        foreach ($source as $inst) {
            // Skip if already exists for target batch
            $exists = AdmissionFeeInstallment::where('program_id', $program->id)
                ->where('batch_id', $request->to_batch_id)
                ->where('installment_number', $inst->installment_number)
                ->exists();
            if ($exists) continue;

            AdmissionFeeInstallment::create([
                'program_id'         => $program->id,
                'batch_id'           => $request->to_batch_id,
                'name'               => $inst->name,
                'amount'             => $inst->amount,
                'installment_number' => $inst->installment_number,
                'due_date'           => $inst->due_date ? $inst->due_date->addDays($offset) : null,
                'description'        => $inst->description,
                'is_active'          => true,
            ]);
            $count++;
        }

        return redirect()->route('admission.fee-installments.index', $program)
            ->with('success', "$count installments duplicated to new batch.");
    }
}
