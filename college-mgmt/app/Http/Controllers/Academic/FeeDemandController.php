<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\FeeDemand;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeDemandController extends Controller
{
    public function index()
    {
        $feeDemands = FeeDemand::with(['student', 'term'])->paginate(15);
        $batches = \App\Models\Batch::with('program')->where('is_active', true)->get();
        $terms   = \App\Models\Term::orderBy('term_number')->get();
        return view('academic.fee-demands.index', compact('feeDemands', 'batches', 'terms'));
    }

    public function create()
    {
        $students = Student::select('id', 'name')->get();
        $terms = Term::select('id', 'name')->get();
        return view('academic.fee-demands.create', compact('students', 'terms'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'term_id' => 'required|exists:terms,id',
            'total_amount' => 'required|numeric|min:0',
            'scholarship_deduction' => 'nullable|numeric|min:0|lte:total_amount',
            'due_date' => 'required|date',
            'status' => 'required|in:pending,partially_paid,fully_paid,overdue',
        ]);

        $validated['scholarship_deduction'] = $validated['scholarship_deduction'] ?? 0;
        $validated['final_amount'] = max(0, $validated['total_amount'] - $validated['scholarship_deduction']);

        $student = Student::findOrFail($validated['student_id']);
        if ($student->status !== 'active') {
            return back()->withErrors(['student_id' => 'Fee demands can be created only for active students.']);
        }

        if (FeeDemand::where('student_id', $validated['student_id'])->where('term_id', $validated['term_id'])->exists()) {
            return back()->withErrors(['term_id' => 'A fee demand already exists for this student and term.']);
        }

        if ($validated['status'] === 'partially_paid') {
            return back()->withErrors(['status' => 'A new fee demand cannot start as partially paid. Record an actual payment or verified proof first.']);
        }

        if ($validated['status'] === 'fully_paid' && $validated['final_amount'] > 0) {
            return back()->withErrors(['status' => 'A new non-zero fee demand cannot be created as fully paid. Record a payment, verified proof, waiver, or scholarship adjustment first.']);
        }

        FeeDemand::create($validated);

        return redirect()->route('academic.fee-demands.index')
            ->with('success', 'Fee demand created successfully');
    }

    public function show(FeeDemand $feeDemand)
    {
        $feeDemand->load(['student.user', 'student.program', 'term']);
        $payments = \App\Models\FeePayment::where('student_id', $feeDemand->student_id)->latest()->take(10)->get();
        return view('academic.fee-demands.show', compact('feeDemand', 'payments'));
    }

    public function edit(FeeDemand $feeDemand)
    {
        $students = Student::select('id', 'name')->get();
        $terms = Term::select('id', 'name')->get();
        return view('academic.fee-demands.edit', compact('feeDemand', 'students', 'terms'));
    }

    public function update(Request $request, FeeDemand $feeDemand)
    {
        if ($feeDemand->status === 'fully_paid') {
            return back()->with('error', 'Fully paid fee demands are locked. Use an audited adjustment or refund workflow instead of editing closed demand history.');
        }

        $validated = $request->validate([
            'total_amount' => 'required|numeric|min:0',
            'scholarship_deduction' => 'nullable|numeric|min:0|lte:total_amount',
            'due_date' => 'required|date',
            'status' => 'required|in:pending,partially_paid,fully_paid,overdue',
        ]);

        $validated['scholarship_deduction'] = $validated['scholarship_deduction'] ?? 0;
        $validated['final_amount'] = max(0, $validated['total_amount'] - $validated['scholarship_deduction']);

        if ($feeDemand->hasFinancialActivity() && $this->changesLedgerFields($feeDemand, $validated)) {
            return back()->withErrors([
                'fee_demand' => 'This fee demand has payment activity and its ledger fields cannot be rewritten. Use an audited adjustment, waiver, refund, or new demand instead.',
            ]);
        }

        if ($validated['status'] === 'partially_paid' && ! $feeDemand->hasFinancialActivity()) {
            return back()->withErrors(['status' => 'A fee demand cannot be marked partially paid without linked payment activity. Record an actual payment or verified proof first.']);
        }

        if ($validated['status'] === 'fully_paid' && $validated['final_amount'] > 0) {
            return back()->withErrors(['status' => 'A non-zero demand cannot be manually closed as fully paid. Record a payment, verified proof, waiver, or scholarship adjustment first.']);
        }

        $feeDemand->update($validated);

        return redirect()->route('academic.fee-demands.show', $feeDemand)
            ->with('success', 'Fee demand updated successfully');
    }

    public function markAsPaid(FeeDemand $feeDemand)
    {
        if ($feeDemand->status === 'fully_paid') {
            return back()->with('error', 'Fee demand is already fully paid.');
        }

        $feeDemand->loadMissing('student');
        if ($feeDemand->student?->status !== 'active') {
            return back()->with('error', 'Fee demands for inactive or archived students cannot be manually marked paid from the standard fee-demand workflow.');
        }

        if ($feeDemand->openBalance() > 0) {
            return back()->withErrors(['fee_demand' => 'This demand still has an open balance. Use fee receipt/payment verification or an approved adjustment instead of manually marking it paid.']);
        }

        $feeDemand->markAsFullyPaid();
        return back()->with('success', 'Fee marked as fully paid');
    }

    public function generateDemands(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'term_id'  => 'required|exists:terms,id',
        ]);

        $batch = \App\Models\Batch::with('program')->findOrFail($request->batch_id);
        $term  = \App\Models\Term::findOrFail($request->term_id);

        // Get fee structure for this program
        $feeStructures = \App\Models\FeeStructure::where('program_id', $batch->program_id)->get();
        $totalFee = $feeStructures->sum('amount');

        if ($totalFee <= 0) {
            return back()->with('error', 'No fee structure defined for this program. Please set up fee structures first.');
        }

        // Get all active students in this batch
        $students = \App\Models\Student::where('batch_id', $batch->id)
            ->where('status', 'active')->get();

        $created = 0;
        $skipped = 0;

        foreach ($students as $student) {
            // Skip if demand already exists for this student+term
            if (\App\Models\FeeDemand::where('student_id', $student->id)->where('term_id', $term->id)->exists()) {
                $skipped++;
                continue;
            }

            $discount = $this->studentAdmissionScholarshipDeduction($student, (float) $totalFee);

            $finalAmount = max(0, $totalFee - $discount);

            \App\Models\FeeDemand::create([
                'student_id'   => $student->id,
                'term_id'      => $term->id,
                'total_amount' => $totalFee,
                'scholarship_deduction' => $discount,
                'final_amount' => $finalAmount,
                'due_date'     => now()->addDays(30)->toDateString(),
                'status'       => 'pending',
            ]);
            $created++;
        }

        return back()->with('success', "Fee demands generated: {$created} created, {$skipped} already existed.");
    }

    public function applyPenalties()
    {
        // Apply 2% penalty per month on overdue demands
        $penaltyRate = 0.02;
        $overdue = \App\Models\FeeDemand::with('student')
            ->whereIn('status', ['pending', 'partially_paid'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->where('penalty_amount', 0) // only apply once
            ->where('final_amount', '>', 0)
            ->whereHas('student', fn ($query) => $query->where('status', 'active'))
            ->get();

        $count = 0;
        foreach ($overdue as $demand) {
            $daysOverdue = now()->diffInDays($demand->due_date);
            $monthsOverdue = max(1, ceil($daysOverdue / 30));
            $penalty = round($demand->final_amount * $penaltyRate * $monthsOverdue, 2);
            $demand->update([
                'penalty_amount' => $penalty,
                'status' => 'overdue',
            ]);
            $count++;
        }

        return back()->with('success', "Penalties applied to {$count} overdue demands.");
    }

    public function destroy(FeeDemand $feeDemand)
    {
        if ($feeDemand->status !== 'pending') {
            return back()->with('error', 'Only untouched pending fee demands can be deleted. Paid, partial, overdue, or closed demands are retained for financial audit.');
        }

        if ($feeDemand->hasFinancialActivity()) {
            return back()->with('error', 'Cannot delete this fee demand because payment requests or installment records are linked to it.');
        }

        $feeDemand->update([
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'cancellation_reason' => 'Cancelled before payment activity.',
        ]);
        $feeDemand->delete();

        return redirect()->route('academic.fee-demands.index')
            ->with('success', 'Fee demand cancelled and retained for audit.');
    }

    private function studentAdmissionScholarshipDeduction(Student $student, float $totalFee): float
    {
        $applicantIds = \App\Models\EnrollmentConfirmation::where('student_id', $student->id)
            ->where('status', 'completed')
            ->pluck('applicant_id');

        if ($applicantIds->isEmpty()) {
            return 0.0;
        }

        $awardedAmount = \App\Models\ApplicantScholarship::whereIn('applicant_id', $applicantIds)
            ->whereIn('status', ['awarded', 'disbursed'])
            ->sum('awarded_amount');

        return min((float) $awardedAmount, $totalFee);
    }

    private function changesLedgerFields(FeeDemand $feeDemand, array $validated): bool
    {
        return number_format((float) $validated['total_amount'], 2, '.', '') !== number_format((float) $feeDemand->total_amount, 2, '.', '')
            || number_format((float) $validated['scholarship_deduction'], 2, '.', '') !== number_format((float) $feeDemand->scholarship_deduction, 2, '.', '')
            || number_format((float) $validated['final_amount'], 2, '.', '') !== number_format((float) $feeDemand->final_amount, 2, '.', '')
            || (string) $validated['due_date'] !== $feeDemand->due_date?->toDateString();
    }
}
