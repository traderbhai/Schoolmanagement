<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\FeeDemand;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Http\Request;

class FeeDemandController extends Controller
{
    public function index()
    {
        $feeDemands = FeeDemand::with(['student', 'term'])->paginate(15);
        return view('academic.fee-demands.index', compact('feeDemands'));
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
        $feeDemand->load(['student', 'term']);
        return view('academic.fee-demands.show', compact('feeDemand'));
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
        $termId = $request->get('term_id');
        $totalFee = $request->get('total_fee', 100000);
        $dueDate = $request->get('due_date');

        $students = Student::where('current_term_id', $termId)->get();
        $demandsGenerated = 0;

        foreach ($students as $student) {
            $scholarshipDeduction = $student->scholarships()
                ->where('status', 'active')
                ->sum(\DB::raw('CASE WHEN fixed_amount IS NOT NULL THEN fixed_amount ELSE 0 END'))
                + ($totalFee * $student->scholarships()
                    ->where('status', 'active')
                    ->avg('percentage') / 100);

            FeeDemand::create([
                'student_id' => $student->id,
                'term_id' => $termId,
                'total_amount' => $totalFee,
                'scholarship_deduction' => $scholarshipDeduction,
                'final_amount' => $totalFee - $scholarshipDeduction,
                'due_date' => $dueDate,
                'status' => 'pending',
            ]);

            $demandsGenerated++;
        }

        return back()->with('success', "$demandsGenerated fee demands generated");
    }

    public function destroy(FeeDemand $feeDemand)
    {
        $feeDemand->delete();
        return redirect()->route('academic.fee-demands.index')
            ->with('success', 'Fee demand deleted successfully');
    }
}
