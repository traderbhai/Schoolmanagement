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
            'scholarship_deduction' => 'nullable|numeric|min:0',
            'due_date' => 'required|date',
            'status' => 'required|in:pending,partially_paid,fully_paid,overdue',
        ]);

        $validated['scholarship_deduction'] = $validated['scholarship_deduction'] ?? 0;
        $validated['final_amount'] = $validated['total_amount'] - $validated['scholarship_deduction'];

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
        $validated = $request->validate([
            'total_amount' => 'required|numeric|min:0',
            'scholarship_deduction' => 'nullable|numeric|min:0',
            'due_date' => 'required|date',
            'status' => 'required|in:pending,partially_paid,fully_paid,overdue',
        ]);

        $validated['scholarship_deduction'] = $validated['scholarship_deduction'] ?? 0;
        $validated['final_amount'] = $validated['total_amount'] - $validated['scholarship_deduction'];

        $feeDemand->update($validated);

        return redirect()->route('academic.fee-demands.show', $feeDemand)
            ->with('success', 'Fee demand updated successfully');
    }

    public function markAsPaid(FeeDemand $feeDemand)
    {
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

            // Check for scholarship discount
            $scholarship = \App\Models\ApplicantScholarship::whereHas('applicant', fn($q) =>
                $q->whereHas('enrollments', fn($eq) => $eq->where('student_id', $student->id))
            )->where('status', 'awarded')->first();

            $discount = 0;
            if ($scholarship && $scholarship->scheme) {
                $pct = $scholarship->scheme->discount_percentage ?? 0;
                $maxDiscount = $scholarship->scheme->max_amount ?? 0;
                $discountByPct = ($totalFee * $pct) / 100;
                $discount = $maxDiscount > 0 ? min($discountByPct, $maxDiscount) : $discountByPct;
            }

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
        $overdue = \App\Models\FeeDemand::where('status', 'pending')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->where('penalty_amount', 0) // only apply once
            ->get();

        $count = 0;
        foreach ($overdue as $demand) {
            $daysOverdue = now()->diffInDays($demand->due_date);
            $monthsOverdue = max(1, ceil($daysOverdue / 30));
            $penalty = round($demand->final_amount * $penaltyRate * $monthsOverdue, 2);
            $demand->update(['penalty_amount' => $penalty]);
            $count++;
        }

        return back()->with('success', "Penalties applied to {$count} overdue demands.");
    }

    public function destroy(FeeDemand $feeDemand)
    {
        $feeDemand->delete();
        return redirect()->route('academic.fee-demands.index')
            ->with('success', 'Fee demand deleted successfully');
    }
}
